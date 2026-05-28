OPUS 4.8
🔴 Causa nº 1 (a mais provável): o DNS do MikroTik quebra o usuário JÁ PAGO
No seu /ip dns static você tem:

add address=127.0.0.1 name=connectivitycheck.gstatic.com
add address=127.0.0.1 name=connectivitycheck.android.com
add address=127.0.0.1 name=clients3.google.com
add address=127.0.0.1 name=captive.apple.com
O problema: essas entradas valem para todo mundo, inclusive quem está liberado (bypassed). Como o DHCP entrega 10.5.50.1 como DNS e você ainda tem o dstnat "Force DNS" redirecionando toda porta 53 pro roteador, até o celular pago pergunta captive.apple.com e recebe 127.0.0.1.

Resultado no celular de quem PAGOU:

iPhone: a verificação de internet falha → mostra "Sem Internet", reabre a telinha de login e os apps não usam o Wi-Fi.
Android: "Conectado, sem internet" → volta sozinho pros dados móveis.
Ou seja: o acesso foi liberado corretamente no seu sistema, mas o celular se recusa a usar porque o teste de conectividade está envenenado. Isso explica perfeitamente "paguei e não consigo conectar / fica sem internet".

Correção (quando tiver acesso ao MikroTik):

/ip dns static remove [find where address=127.0.0.1]
/ip dns cache flush

O hotspot já intercepta o tráfego de quem não pagou e abre o portal sozinho — essas 4 entradas não são necessárias e só atrapalham quem pagou.

🟡 Causa nº 2 (MikroTik): a telinha do portal às vezes nem abre
Pelo mesmo motivo acima, em alguns aparelhos a telinha "Entrar na rede" nem aparece (o teste resolve pra 127.0.0.1 e morre no próprio aparelho, então o hotspot nunca redireciona). Some-se a isso que você bloqueia DoT (853) mas não o DoH (DNS over HTTPS na 443), que celulares modernos usam — alguns burlam a detecção. Remover as entradas 127.0.0.1 já resolve a maior parte.

🟢 No servidor, o que dá pra melhorar agora (pouca coisa)
Código morto/contraditório: isRandomizedMac() em MikrotikApiController.php:21 nunca é usado e contradiz a filosofia correta do HotspotIdentity (MAC randomizado é válido). Pode remover pra não confundir.
Segurança — verifique o webhook genérico: webhook() em PaymentController.php:902 libera acesso só com transaction_id + status=approved, sem nenhuma validação de assinatura. Os webhooks do Santander/Woovi são seguros (passam por serviço), mas se essa rota genérica estiver exposta, qualquer um pode dar acesso grátis. Vale confirmar se ela está roteada/protegida.
Reforçar recuperação por telefone na própria tela do portal: hoje a re-associação de MAC novo depende do cookie wt_user. Cookie em mini-browser de captive portal (iOS CNA) às vezes se perde. Dá pra adicionar um botão "Já paguei / recuperar acesso" que confirma pelo telefone e re-libera na hora — você já tem a lógica de busca por telefone, é só expor no front.
🛠️ Como diagnosticar um caso específico AGORA (sem ir no ônibus)
Você já tem endpoints prontos. Quando um passageiro reclamar, peça o MAC dele e rode:

https://www.tocantinstransportewifi.com.br/api/mikrotik/check-mac?token=mikrotik-sync-2024&mac=XX:XX:XX:XX:XX:XX
Isso te diz se o sistema acha que ele deveria estar liberado e mostra os pagamentos. Se estiver tudo certo no banco mas ele segue sem net → confirma que o problema é o DNS do MikroTik (causa nº 1).

E pra forçar liberação imediata:

.../api/mikrotik/force-liberate?token=mikrotik-sync-2024&mac=XX:XX:XX:XX:XX:XX
Conclusão: seu projeto está bem feito. Não precisa reescrever nada grande no servidor. O ganho real (provavelmente 80% das reclamações) vem de remover as 4 entradas 127.0.0.1 do DNS do MikroTik assim que tiver acesso.

Quer que eu já deixe pronto algum desses itens do servidor? Posso (a) remover o código morto, (b) adicionar a verificação/proteção no webhook genérico, ou (c) montar o botão "Já paguei — recuperar por telefone" no portal. É só dizer qual.

