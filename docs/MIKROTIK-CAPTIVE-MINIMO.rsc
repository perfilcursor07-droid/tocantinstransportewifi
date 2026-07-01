# =============================================================================
# MIKROTIK — CORREÇÃO MÍNIMA (Captive Portal iPhone/Android)
# Compatível com config atual (RouterOS 7.20.x, hAP ac²)
# Serial exemplo: HH60A2NSBE7
#
# O QUE ESTE ARQUIVO FAZ:
#   1. DNS captive → 10.5.50.1 (popup "Entrar na rede" no iPhone/Android)
#   2. registrarMacs a cada 15s (hoje 1min — não afeta captive, só MAC mais rápido)
#   3. syncPagos URL com www (padronização — não quebra nada)
#
# O QUE NÃO MEXE (fluxo que já funciona):
#   - login.html / redirect / walled-garden / firewall / hotspot profile
#   - URL final: ...?source=mikrotik&captive=true&mac=...&ip=...
#
# ANTES: /system backup save name=backup-antes-captive
# =============================================================================

# -----------------------------------------------------------------------------
# PARTE 1 — CAPTIVE PORTAL (principal para iPhone/Android)
# Problema atual: 127.0.0.1 faz o celular testar em SI MESMO.
# O hotspot do MikroTik NÃO intercepta isso → popup não aparece.
# Solução: apontar para 10.5.50.1 → HTTP vai pro roteador → login.html
# -----------------------------------------------------------------------------

/ip dns static set [find name=captive.apple.com] address=10.5.50.1 comment="Captive CNA"
/ip dns static set [find name=connectivitycheck.gstatic.com] address=10.5.50.1 comment="Captive CNA"
/ip dns static set [find name=connectivitycheck.android.com] address=10.5.50.1 comment="Captive CNA"
/ip dns static set [find name=clients3.google.com] address=10.5.50.1 comment="Captive CNA"

# Domínios extras (iOS recente + Android 12+). Ignora se já existir.
:do { /ip dns static add name=www.apple.com address=10.5.50.1 comment="Captive CNA" } on-error={}
:do { /ip dns static add name=gsp1-ssl.ls.apple.com address=10.5.50.1 comment="Captive CNA" } on-error={}
:do { /ip dns static add name=www.appleiphonecell.com address=10.5.50.1 comment="Captive CNA" } on-error={}
:do { /ip dns static add name=clients4.google.com address=10.5.50.1 comment="Captive CNA" } on-error={}
:do { /ip dns static add name=www.msftconnecttest.com address=10.5.50.1 comment="Captive CNA" } on-error={}

# Conferir
/ip dns static print where comment="Captive CNA"

# -----------------------------------------------------------------------------
# PARTE 2 — registrarMacs mais rápido (15s em vez de 1min)
# Não altera captive. Só reporta MAC/IP pro Laravel mais cedo.
# -----------------------------------------------------------------------------

/system scheduler set [find name=registrarMacsScheduler] interval=15s comment="Registra MACs na API (15s)"

# -----------------------------------------------------------------------------
# PARTE 3 — syncPagos: URL com www (mesma API, mesmo token, mesmo formato)
# Mantém &mid= do serial — igual ao script que você já tem.
# -----------------------------------------------------------------------------

/system script set [find name=syncPagos] source={
:local mid [/system routerboard get serial-number]
:local url ("https://www.tocantinstransportewifi.com.br/api/mikrotik/check-paid-users-lite\?token=mikrotik-sync-2024&mid=" . $mid)
:local bypassComment "PAGO-AUTO"
:local liberados 0
:local removidos 0

:log info "=== INICIANDO SYNC ==="

:do {
    :local result [/tool fetch url=$url mode=https http-method=get output=user check-certificate=no as-value]

    :if (($result->"status") = "finished") do={
        :local data ($result->"data")

        :if ([:pick $data 0 2] = "OK") do={
            :local pos 0
            :local dataLen [:len $data]

            :while ($pos < $dataLen) do={
                :local lineEnd [:find $data "\n" $pos]
                :if ([:typeof $lineEnd] = "nil") do={
                    :set lineEnd $dataLen
                }

                :local line [:pick $data $pos $lineEnd]
                :set pos ($lineEnd + 1)

                :if ([:len $line] < 4) do={
                } else={
                    :local prefix [:pick $line 0 2]
                    :local mac [:pick $line 2 [:len $line]]

                    :if ([:pick $mac ([:len $mac] - 1) [:len $mac]] = "\r") do={
                        :set mac [:pick $mac 0 ([:len $mac] - 1)]
                    }

                    :if ([:len $mac] = 17) do={
                        :if ($prefix = "L:") do={
                            :local existing [/ip hotspot ip-binding find mac-address=$mac comment=$bypassComment]
                            :if ([:len $existing] = 0) do={
                                :log info ("[+] Liberando MAC: " . $mac)
                                :do {/ip hotspot active remove [find mac-address=$mac]} on-error={}
                                :do {/ip hotspot host remove [find mac-address=$mac]} on-error={}
                                :do {
                                    /ip hotspot ip-binding add mac-address=$mac type=bypassed comment=$bypassComment
                                    :set liberados ($liberados + 1)
                                } on-error={
                                    :log warning ("Erro ao criar binding: " . $mac)
                                }
                            }
                        }

                        :if ($prefix = "R:") do={
                            :local toRemove [/ip hotspot ip-binding find mac-address=$mac comment=$bypassComment]
                            :if ([:len $toRemove] > 0) do={
                                :log warning ("[-] Removendo expirado: " . $mac)
                                /ip hotspot ip-binding remove $toRemove
                                :do {/ip hotspot active remove [find mac-address=$mac]} on-error={}
                                :do {/ip hotspot host remove [find mac-address=$mac]} on-error={}
                                :set removidos ($removidos + 1)
                            }
                        }
                    }
                }
            }
        } else={
            :log warning ("Resposta API invalida: " . $data)
        }
    } else={
        :log error ("Fetch failed: " . ($result->"status"))
    }
} on-error={
    :log error "Erro ao consultar API de sync"
}

:if (($liberados > 0) || ($removidos > 0)) do={
    :log info ("=== SYNC (" . $mid . "): +" . $liberados . " liberados, -" . $removidos . " removidos ===")
} else={
    :log info "=== SYNC: Nenhuma alteracao ==="
}
}

:log info "=== CAPTIVE MINIMO APLICADO ==="
