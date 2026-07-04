# Bugfix Requirements Document

## Introduction

MikroTik hAP ac² routers (RouterOS 7.20.7, 16 MB flash) deployed on the Tocantins Transporte WiFi buses progressively run out of writable flash storage until `free-hdd-space` reaches 0. Once flash is exhausted, the router cannot persist any configuration change: every write fails with "could not save configuration changes, not enough storage space available.", even file deletions fail with "action failed (6)", and `write-sect-since-reboot: 0` confirms no flash writes succeed. Rebooting does not recover the space.

Live diagnosis ruled out bloated config tables (hotspot cookies=0, hosts=3, firewall address-list=0, DHCP leases=43, DNS cache=65) and RAM caches (clearing cookies/DNS did not change `free-hdd-space`). The only continuously-growing, flash-persisted artifact tied to the application is the bypass list: the Laravel sync feed (`/api/mikrotik/check-paid-users-lite`, seen in logs as "📡 MikroTik Lite sync" with `macs_liberar` / `macs_remover`) is applied by the router-side `syncPagos` script as **persistent** `/ip hotspot ip-binding` entries (`type=bypassed comment="PAGO-AUTO"`). 101 such bindings were present. Because the feed broadcasts up to ~1000 active MACs to every bus and persistent bindings are written to flash, the writable config store grows over time and, combined with the RouterOS 7.20 footprint on a 16 MB device, wedges the flash.

When flash is wedged, the router can no longer add or remove bindings, update captive-portal/DNS fixes, or persist any config. The user-visible impact is "paguei e não conecta" — paying passengers are not liberated because their MAC cannot be added — and captive-portal fixes cannot be saved. This affects every bus over time.

The fix must make the liberation/bypass mechanism flash-safe so repeated sync cycles do not accumulate persistent flash writes, while preserving correct liberation of paying users and the captive portal / walled garden behavior.

## Bug Analysis

### Current Behavior (Defect)

The router-side sync script persists each liberated MAC as a permanent config entry, so the writable flash store grows with every sync cycle until it is exhausted on the 16 MB device.

1.1 WHEN a MAC is present in the sync feed's liberation list (`L:<MAC>`) THEN the router-side script adds it as a persistent `/ip hotspot ip-binding` (`type=bypassed`, `comment="PAGO-AUTO"`) entry that is written to flash

1.2 WHEN sync cycles run repeatedly over time on a 16 MB flash device THEN the accumulated persistent bindings (combined with the RouterOS 7.20 footprint) drive `free-hdd-space` toward 0 and the router reaches a state where `write-sect-since-reboot` is 0

1.3 WHEN `free-hdd-space` is 0 THEN any configuration change fails with "could not save configuration changes, not enough storage space available." and file/binding deletions fail with "action failed (6)"

1.4 WHEN flash is exhausted and a paying user's MAC needs to be added THEN the binding cannot be written, so the paid user is not liberated ("paguei e não conecta")

1.5 WHEN the router is rebooted while flash is exhausted THEN the free space is not recovered and the defective state persists

### Expected Behavior (Correct)

Liberation must be applied in a way that does not consume writable flash, so repeated sync cycles keep `free-hdd-space` stable.

2.1 WHEN a MAC is present in the sync feed's liberation list THEN the router SHALL grant bypass access using a non-persistent (RAM-resident / dynamic) mechanism that is not written to flash (e.g. dynamic `ip-binding` or a firewall `address-list` entry keyed by MAC/IP with a timeout)

2.2 WHEN sync cycles run repeatedly over time on a 16 MB flash device THEN the router SHALL keep `free-hdd-space` stable (it SHALL NOT trend toward 0 as a result of applying the bypass list)

2.3 WHEN a paying user's MAC appears in the liberation list THEN the router SHALL liberate that user without requiring a flash write, so liberation succeeds even when writable flash is at or near 0

2.4 WHEN a MAC leaves the liberation list or appears in the removal list (`R:<MAC>`) THEN the router SHALL revoke that MAC's bypass access without requiring a persistent flash write

2.5 WHEN a router is already wedged with `free-hdd-space` = 0 THEN there SHALL be an operational recovery path (e.g. cleanup of accumulated persistent bindings / netinstall) documented so the device can be returned to service, while the code fix prevents recurrence

### Unchanged Behavior (Regression Prevention)

The functional outcome of the payment-to-internet flow and the captive portal must be preserved; only the storage mechanism changes.

3.1 WHEN a user has paid and their MAC is in the active/liberation feed THEN the system SHALL CONTINUE TO give that user full internet access (bypass of the hotspot)

3.2 WHEN a user's access expires or their MAC is removed THEN the system SHALL CONTINUE TO revoke that user's internet access

3.3 WHEN an unpaid device connects THEN the system SHALL CONTINUE TO redirect it to the captive portal and keep the walled garden (payment portal, PIX gateways, bank domains, SSL/OCSP hosts) reachable

3.4 WHEN the Laravel sync endpoint returns liberation and removal lists (`macs_liberar` / `macs_remover`) THEN the system SHALL CONTINUE TO broadcast the correct set of active and expired MACs to each bus as it does today

3.5 WHEN a device uses MAC randomization or reconnects with a new MAC THEN the system SHALL CONTINUE TO liberate valid real MACs and clean up orphaned/expired MACs as it does today

## Bug Condition and Properties

### Bug Condition — `isBugCondition(X)`

```pascal
FUNCTION isBugCondition(X)
  INPUT: X of type SyncCycle          // one application of the sync feed on a router
  OUTPUT: boolean

  // The bug is triggered whenever a liberation is applied as a
  // persistent (flash-written) binding on a flash-constrained device.
  RETURN X.liberationMechanism = PERSISTENT_IP_BINDING
         AND X.device.flashIsConstrained = true   // e.g. 16 MB hAP ac²
END FUNCTION
```

### Property — Fix Checking (behavior for buggy inputs)

```pascal
// Property: applying liberations must not consume writable flash
FOR ALL X WHERE isBugCondition(X) DO
  before ← X.device.freeHddSpace
  applyLiberations'(X)                 // F' = fixed apply routine
  after  ← X.device.freeHddSpace
  ASSERT after >= before               // no net flash consumption from bypass
    AND liberatedUsersHaveInternet(X)  // paid users still liberated
    AND writeSectSinceReboot(X) not forced to 0 by the bypass mechanism
END FOR
```

```pascal
// Property: repeated cycles do not trend free space to 0
FOR ALL sequences [X1, X2, ... Xn] WHERE isBugCondition(Xi) DO
  ASSERT freeHddSpace after Xn is stable (bounded, not monotonically decreasing
         toward 0 due to accumulated bypass entries)
END FOR
```

### Preservation — Preservation Checking (behavior for non-buggy inputs)

```pascal
// Property: for all inputs that don't trigger the bug, behavior is unchanged.
// F  = original apply routine, F' = fixed apply routine.
FOR ALL X WHERE NOT isBugCondition(X) DO
  ASSERT F(X) = F'(X)
END FOR

// In functional terms, regardless of the storage mechanism:
FOR ALL X DO
  ASSERT paidUserGetsInternet(F'(X))      = paidUserGetsInternet(F(X))
    AND expiredUserRevoked(F'(X))         = expiredUserRevoked(F(X))
    AND unpaidRedirectedToPortal(F'(X))   = unpaidRedirectedToPortal(F(X))
    AND walledGardenReachable(F'(X))      = walledGardenReachable(F(X))
END FOR
```

**Key Definitions:**
- **F** — the original behavior: liberations applied as persistent `/ip hotspot ip-binding` entries written to flash.
- **F'** — the fixed behavior: liberations applied via a non-persistent (RAM/dynamic, or timeout-based address-list) mechanism that does not consume writable flash.
- **Counterexample (demonstrates the bug):** a 16 MB hAP ac² that has processed many sync cycles reaches `free-hdd-space: 0` and `write-sect-since-reboot: 0`; a newly paid MAC then fails to be added ("could not save configuration changes, not enough storage space available.") and the passenger has no internet despite paying.
