# Config limpa para hAP ac2 - RouterOS 7.12.1 (pacote routeros apenas)
# Tocantins Transporte WiFi
# USO: /import file-name=config-limpo-hap-ac2-v712.rsc

/interface bridge
add comment="Bridge para Hotspot WiFi" name=wifi-hotspot

/interface wireless security-profiles
add authentication-types="" mode=none name=open-security

/interface wireless
set [ find default-name=wlan1 ] band=2ghz-b/g/n channel-width=20/40mhz-XX disabled=no mode=ap-bridge security-profile=open-security ssid=TocantinsTransporteWiFi wireless-protocol=802.11 country=brazil
set [ find default-name=wlan2 ] band=5ghz-a/n/ac channel-width=20/40/80mhz-XXXX disabled=no mode=ap-bridge security-profile=open-security ssid=TocantinsTransporteWiFi wireless-protocol=802.11 country=brazil

/ip hotspot profile
add dns-name=10.5.50.1 hotspot-address=10.5.50.1 html-directory=flash/hotspot http-cookie-lifetime=1d install-hotspot-queue=no login-by=cookie,http-chap,http-pap name=hsprof-tocantins

/ip pool
add name=hs-pool ranges=10.5.50.10-10.5.50.250

/ip dhcp-server
add address-pool=hs-pool interface=wifi-hotspot lease-time=2h name=hotspot-dhcp

/ip hotspot
add address-pool=hs-pool disabled=no interface=wifi-hotspot name=tocantins-hotspot profile=hsprof-tocantins

/interface bridge port
add bridge=wifi-hotspot interface=ether5
add bridge=wifi-hotspot interface=wlan1
add bridge=wifi-hotspot interface=wlan2

/ip firewall connection tracking
set generic-timeout=5m tcp-established-timeout=30m

/ip address
add address=10.5.50.1/24 interface=wifi-hotspot network=10.5.50.0
add address=192.168.137.2/24 comment=WAN-PC-Temp interface=ether2 network=192.168.137.0

/ip dhcp-client
add comment=WAN-Starlink interface=ether1

/ip dhcp-server network
add address=10.5.50.0/24 comment="hotspot network" dns-server=10.5.50.1,1.1.1.1,8.8.8.8 gateway=10.5.50.1

/ip dns
set allow-remote-requests=yes cache-max-ttl=1d cache-size=4096KiB servers=1.1.1.1,8.8.8.8

/ip dns static
add address=104.248.185.39 comment="Portal Principal" name=tocantinstransportewifi.com.br type=A
add address=104.248.185.39 comment="Portal WWW" name=www.tocantinstransportewifi.com.br type=A
add address=104.248.185.39 comment="Portal Curto" name=portal.wifi type=A
add address=104.248.185.39 comment="Portal Curto" name=conectar.wifi type=A
add address=10.5.50.1 comment="Login Alternativo" name=login.wifi type=A
add address=10.5.50.1 comment="Login Hotspot" name=hotspot.wifi type=A
add address=10.5.50.1 comment="Hotspot Login Local" name=login.tocantinswifi.local type=A

/ip firewall filter
add action=accept chain=input comment=Allow-ICMP protocol=icmp
add action=accept chain=output comment=Allow-Output
add action=drop chain=forward comment="Block DoT" dst-port=853 in-interface=wifi-hotspot protocol=tcp
add action=accept chain=hs-input comment="Allow DNS to router (UDP)" dst-port=53 protocol=udp
add action=accept chain=hs-input comment="Allow DNS to router (TCP)" dst-port=53 protocol=tcp
add action=drop chain=input comment="Drop API outros IPs" dst-port=8728 protocol=tcp src-address=!104.248.185.39
add action=accept chain=input comment="API Laravel" dst-port=8728 protocol=tcp src-address=104.248.185.39

/ip firewall nat
add action=masquerade chain=srcnat comment=NAT-Starlink out-interface=ether1
add action=masquerade chain=srcnat comment=NAT-PC-Temp out-interface=ether2
add action=redirect chain=dstnat comment="Force DNS UDP" dst-port=53 in-interface=wifi-hotspot protocol=udp to-ports=53
add action=redirect chain=dstnat comment="Force DNS TCP" dst-port=53 in-interface=wifi-hotspot protocol=tcp to-ports=53

/ip hotspot walled-garden
add comment="Portal Principal" dst-host=tocantinstransportewifi.com.br
add comment="Portal WWW" dst-host=*.tocantinstransportewifi.com.br
add comment="Portal: Tailwind CDN" dst-host=cdn.tailwindcss.com
add comment="Portal: Google Fonts API" dst-host=fonts.googleapis.com
add comment="Portal: Google Fonts Static" dst-host=fonts.gstatic.com
add comment="Portal: CDNJS" dst-host=cdnjs.cloudflare.com
add comment="QR Code API" dst-host=api.qrserver.com
add comment="QR Charts" dst-host=chart.googleapis.com

/ip hotspot walled-garden ip
add action=accept comment="IP: Portal" disabled=no dst-address=104.248.185.39
add action=accept comment="IP: Router DNS" disabled=no dst-address=10.5.50.1
add action=accept comment="IP: Google DNS" disabled=no dst-address=8.8.8.8
add action=accept comment="IP: Google DNS 2" disabled=no dst-address=8.8.4.4
add action=accept comment="IP: Cloudflare DNS" disabled=no dst-address=1.1.1.1
add action=accept comment="IP: Cloudflare DNS 2" disabled=no dst-address=1.0.0.1
add action=accept comment="Cloudflare CDN" disabled=no dst-address=104.16.0.0/12

/ip route
add comment=Rota-PC-Temp distance=2 gateway=192.168.137.1

/ip service
set ftp disabled=yes
set telnet disabled=yes
set www port=8081
set www-ssl disabled=no
set api-ssl disabled=yes

/system clock
set time-zone-name=America/Araguaina

/system logging
add topics=hotspot

/system scheduler
add comment="Sincroniza usuarios pagos com API (15s)" interval=15s name=syncPagosScheduler on-event="/system script run syncPagos" policy=read,write,policy,test start-time=startup
add comment="Registra MACs conectados na API (1min)" interval=1m name=registrarMacsScheduler on-event="/system script run registrarMacs" policy=read,write,policy,test start-time=startup

/system script
add dont-require-permissions=no name=registrarMacs owner=admin policy=read,write,policy,test source="\r\
    \n:local token \"mikrotik-sync-2024\"\r\
    \n:local mid [/system routerboard get serial-number]\r\
    \n:local registrados 0\r\
    \n:foreach lease in=[/ip dhcp-server lease find where dynamic=yes] do={\r\
    \n  :local mac [/ip dhcp-server lease get \$lease mac-address]\r\
    \n  :local ip [/ip dhcp-server lease get \$lease address]\r\
    \n  :if (([:len \$mac] = 17) && ([:len \$ip] > 0)) do={\r\
    \n    :local url (\"https://www.tocantinstransportewifi.com.br/api/mikrotik/register-mac\?token=\" . \$token . \"&mac=\" . \$mac . \"&ip=\" . \$ip . \"&mid=\" . \$mid)\r\
    \n    :do {\r\
    \n      /tool fetch url=\$url http-method=get mode=https keep-result=no check-certificate=no\r\
    \n      :set registrados (\$registrados + 1)\r\
    \n    } on-error={ :log warning (\"Falha ao registrar: \" . \$mac) }\r\
    \n  }\r\
    \n}\r\
    \n:log info (\"=== REGISTRO: \" . \$registrados . \" MACs (\" . \$mid . \") ===\")\r\
    \n"

add dont-require-permissions=no name=syncPagos owner=admin policy=read,write,policy,test source="\r\
    \n:local mid [/system routerboard get serial-number]\r\
    \n:local url (\"https://tocantinstransportewifi.com.br/api/mikrotik/check-paid-users-lite\?token=mikrotik-sync-2024&mid=\" . \$mid)\r\
    \n:local bypassComment \"PAGO-AUTO\"\r\
    \n:local liberados 0\r\
    \n:local removidos 0\r\
    \n:log info \"=== INICIANDO SYNC ===\"\r\
    \n:do {\r\
    \n  :local result [/tool fetch url=\$url mode=https http-method=get output=user check-certificate=no as-value]\r\
    \n  :if ((\$result->\"status\") = \"finished\") do={\r\
    \n    :local data (\$result->\"data\")\r\
    \n    :if ([:pick \$data 0 2] = \"OK\") do={\r\
    \n      :local pos 0\r\
    \n      :local dataLen [:len \$data]\r\
    \n      :while (\$pos < \$dataLen) do={\r\
    \n        :local lineEnd [:find \$data \"\\n\" \$pos]\r\
    \n        :if ([:typeof \$lineEnd] = \"nil\") do={ :set lineEnd \$dataLen }\r\
    \n        :local line [:pick \$data \$pos \$lineEnd]\r\
    \n        :set pos (\$lineEnd + 1)\r\
    \n        :if ([:len \$line] >= 4) do={\r\
    \n          :local prefix [:pick \$line 0 2]\r\
    \n          :local mac [:pick \$line 2 [:len \$line]]\r\
    \n          :if ([:pick \$mac ([:len \$mac] - 1) [:len \$mac]] = \"\\r\") do={\r\
    \n            :set mac [:pick \$mac 0 ([:len \$mac] - 1)]\r\
    \n          }\r\
    \n          :if ([:len \$mac] = 17) do={\r\
    \n            :if (\$prefix = \"L:\") do={\r\
    \n              :local existing [/ip hotspot ip-binding find mac-address=\$mac comment=\$bypassComment]\r\
    \n              :if ([:len \$existing] = 0) do={\r\
    \n                :log info (\"[+] Liberando MAC: \" . \$mac)\r\
    \n                :do {/ip hotspot active remove [find mac-address=\$mac]} on-error={}\r\
    \n                :do {/ip hotspot host remove [find mac-address=\$mac]} on-error={}\r\
    \n                :do {\r\
    \n                  /ip hotspot ip-binding add mac-address=\$mac type=bypassed comment=\$bypassComment\r\
    \n                  :set liberados (\$liberados + 1)\r\
    \n                } on-error={ :log warning (\"Erro ao criar binding: \" . \$mac) }\r\
    \n              }\r\
    \n            }\r\
    \n            :if (\$prefix = \"R:\") do={\r\
    \n              :local toRemove [/ip hotspot ip-binding find mac-address=\$mac comment=\$bypassComment]\r\
    \n              :if ([:len \$toRemove] > 0) do={\r\
    \n                :log warning (\"[-] Removendo expirado: \" . \$mac)\r\
    \n                /ip hotspot ip-binding remove \$toRemove\r\
    \n                :do {/ip hotspot active remove [find mac-address=\$mac]} on-error={}\r\
    \n                :do {/ip hotspot host remove [find mac-address=\$mac]} on-error={}\r\
    \n                :set removidos (\$removidos + 1)\r\
    \n              }\r\
    \n            }\r\
    \n          }\r\
    \n        }\r\
    \n      }\r\
    \n    } else={ :log warning (\"Resposta API invalida: \" . \$data) }\r\
    \n  } else={ :log error (\"Fetch failed: \" . (\$result->\"status\")) }\r\
    \n} on-error={ :log error \"Erro ao consultar API de sync\" }\r\
    \n:if ((\$liberados > 0) || (\$removidos > 0)) do={\r\
    \n  :log info (\"=== SYNC (\" . \$mid . \"): +\" . \$liberados . \" liberados, -\" . \$removidos . \" removidos ===\")\r\
    \n} else={ :log info \"=== SYNC: Nenhuma alteracao ===\" }\r\
    \n"
