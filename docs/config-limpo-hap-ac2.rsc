# Config limpa para hAP ac² - Tocantins Transporte WiFi
# Gerado em 04/07/2026
# USO: Após /system reset-configuration no-defaults=yes skip-backup=yes
#      Importar com: /import file-name=config-limpo-hap-ac2.rsc
#
# REMOVIDOS (em relação ao export original):
#   - 4 entradas DNS 127.0.0.1 (quebravam captive portal)
#   - 15 ip-bindings PAGO-AUTO (o sync readiciona em 15s)
#   - MAC addresses das interfaces ethernet (cada roteador tem MAC próprio)
#   - MAC addresses do WiFi (cada roteador tem MAC próprio)
#   - bridgeLocal não usada (economiza flash)
#
# ADICIONADO:
#   - /ip service set www port=8081 (libera porta 80 pro hotspot)

/interface bridge
add comment="Bridge para Hotspot WiFi" name=wifi-hotspot

/interface list
add comment=defconf name=WAN
add comment=defconf name=LAN

/interface wifi datapath
add bridge=wifi-hotspot comment="Datapath Hotspot" name=capdp
add bridge=wifi-hotspot name=dp-hotspot

/interface wifi security
add authentication-types="" comment="Rede Aberta" name=open-security

/interface wifi configuration
add country=Brazil name=tocantins-2g security=open-security ssid=TocantinsTransporteWiFi
add country=Brazil name=tocantins-5g security=open-security ssid=TocantinsTransporteWiFi

/interface wifi
set [ find default-name=wifi1 ] channel.band=2ghz-n .width=20/40mhz configuration=tocantins-2g datapath=dp-hotspot disabled=no
set [ find default-name=wifi2 ] channel.band=5ghz-ac .width=20/40/80mhz configuration=tocantins-5g datapath=dp-hotspot disabled=no

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
add bridge=wifi-hotspot interface=wifi1
add bridge=wifi-hotspot interface=wifi2

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
add dont-require-permissions=no name=registrarMacs owner=admin policy=read,write,policy,test source="\
\n:local token \"mikrotik-sync-2024\"\
\n:local mid [/system routerboard get serial-number]\
\n\
\n:local registrados 0\
\n\
\n:foreach lease in=[/ip dhcp-server lease find where dynamic=yes] do={\
\n    :local mac [/ip dhcp-server lease get \$lease mac-address]\
\n    :local ip [/ip dhcp-server lease get \$lease address]\
\n    \
\n    :if (([:len \$mac] = 17) && ([:len \$ip] > 0)) do={\
\n        :local url (\"https://www.tocantinstransportewifi.com.br/api/mikrotik/register-mac\?token=\" . \$token . \"&mac=\" . \$mac . \"&ip=\" . \$ip . \"&mid=\" . \$mid)\
\n        \
\n        :do {\
\n            /tool fetch url=\$url http-method=get mode=https keep-result=no check-certificate=no\
\n            :set registrados (\$registrados + 1)\
\n        } on-error={\
\n            :log warning (\"Falha ao registrar: \" . \$mac)\
\n        }\
\n    }\
\n}\
\n\
\n:log info (\"=== REGISTRO: \" . \$registrados . \" MACs (\" . \$mid . \") ===\")\
\n"

add dont-require-permissions=no name=syncPagos owner=admin policy=read,write,policy,test source="\
\n:local mid [/system routerboard get serial-number]\
\n:local url (\"https://tocantinstransportewifi.com.br/api/mikrotik/check-paid-users-lite\?token=mikrotik-sync-2024&mid=\" . \$mid)\
\n:local bypassComment \"PAGO-AUTO\"\
\n:local liberados 0\
\n:local removidos 0\
\n\
\n:log info \"=== INICIANDO SYNC ===\"\
\n\
\n:do {\
\n    :local result [/tool fetch url=\$url mode=https http-method=get output=user check-certificate=no as-value]\
\n    \
\n    :if ((\$result->\"status\") = \"finished\") do={\
\n        :local data (\$result->\"data\")\
\n        \
\n        :if ([:pick \$data 0 2] = \"OK\") do={\
\n            :local pos 0\
\n            :local dataLen [:len \$data]\
\n            \
\n            :while (\$pos < \$dataLen) do={\
\n                :local lineEnd [:find \$data \"\\n\" \$pos]\
\n                :if ([:typeof \$lineEnd] = \"nil\") do={\
\n                    :set lineEnd \$dataLen\
\n                }\
\n                \
\n                :local line [:pick \$data \$pos \$lineEnd]\
\n                :set pos (\$lineEnd + 1)\
\n                \
\n                :if ([:len \$line] < 4) do={\
\n                } else={\
\n                    :local prefix [:pick \$line 0 2]\
\n                    :local mac [:pick \$line 2 [:len \$line]]\
\n                    \
\n                    :if ([:pick \$mac ([:len \$mac] - 1) [:len \$mac]] = \"\\r\") do={\
\n                        :set mac [:pick \$mac 0 ([:len \$mac] - 1)]\
\n                    }\
\n                    \
\n                    :if ([:len \$mac] = 17) do={\
\n                        :if (\$prefix = \"L:\") do={\
\n                            :local existing [/ip hotspot ip-binding find mac-address=\$mac comment=\$bypassComment]\
\n                            :if ([:len \$existing] = 0) do={\
\n                                :log info (\"[+] Liberando MAC: \" . \$mac)\
\n                                :do {/ip hotspot active remove [find mac-address=\$mac]} on-error={}\
\n                                :do {/ip hotspot host remove [find mac-address=\$mac]} on-error={}\
\n                                :do {\
\n                                    /ip hotspot ip-binding add mac-address=\$mac type=bypassed comment=\$bypassComment\
\n                                    :set liberados (\$liberados + 1)\
\n                                } on-error={\
\n                                    :log warning (\"Erro ao criar binding: \" . \$mac)\
\n                                }\
\n                            }\
\n                        }\
\n                        \
\n                        :if (\$prefix = \"R:\") do={\
\n                            :local toRemove [/ip hotspot ip-binding find mac-address=\$mac comment=\$bypassComment]\
\n                            :if ([:len \$toRemove] > 0) do={\
\n                                :log warning (\"[-] Removendo expirado: \" . \$mac)\
\n                                /ip hotspot ip-binding remove \$toRemove\
\n                                :do {/ip hotspot active remove [find mac-address=\$mac]} on-error={}\
\n                                :do {/ip hotspot host remove [find mac-address=\$mac]} on-error={}\
\n                                :set removidos (\$removidos + 1)\
\n                            }\
\n                        }\
\n                    }\
\n                }\
\n            }\
\n        } else={\
\n            :log warning (\"Resposta API invalida: \" . \$data)\
\n        }\
\n    } else={\
\n        :log error (\"Fetch failed: \" . (\$result->\"status\"))\
\n    }\
\n} on-error={\
\n    :log error \"Erro ao consultar API de sync\"\
\n}\
\n\
\n:if ((\$liberados > 0) || (\$removidos > 0)) do={\
\n    :log info (\"=== SYNC (\" . \$mid . \"): +\" . \$liberados . \" liberados, -\" . \$removidos . \" removidos ===\")\
\n} else={\
\n    :log info \"=== SYNC: Nenhuma alteracao ===\"\
\n}\
\n"
