# Plano por intervalo

## Configuracao e instalacao

Aplicar a migration antes de ativar a opcao no painel:

```sh
php artisan migrate --path=database/migrations/2026_09_10_000001_create_interval_access_days_table.php --force
php artisan view:clear
```

Em `/admin/settings`, ativar Plano por intervalo, definir o limite (2 a 90 dias)
e o preco de 12h por dia. A opcao nasce desativada. Desativar vendas nao cancela
intervalos ja pagos. Nenhuma alteracao nos scripts do MikroTik e necessaria.

## Regra de compra e uso

- As duas datas contam: de 10 a 11 sao duas diarias. Padrao: hoje ate amanha.
- Com base de R$6,99, duas diarias de 12h custam R$13,98; de 24h, R$27,96.
- O servidor calcula o valor e guarda as condicoes no pagamento. O intervalo
  nao recebe o desconto por video dos planos avulsos.
- Cada data permite uma janela de horas corridas. Reconectar nao reinicia.
- Se iniciar as 21h, as 12h terminam as 09h do dia seguinte. Enquanto essa janela
  estiver ativa, nao consome a diaria seguinte. Ao terminar, uma nova abertura
  do portal pode iniciar a diaria da data atual, se comprada e ainda nao usada.
- A ultima diaria tambem pode terminar no dia seguinte ao fim do intervalo.
- Datas nao usadas nao acumulam. Horas offline dentro de uma janela contam.
- Se o passageiro iniciou o pagamento no aparelho (bypass aprovado para o mesmo
  pagamento, usuario e MAC nos 15 minutos anteriores), a confirmacao inicia a
  primeira diaria no horario do pagamento, sem depender de voltar do app do banco.
- Sem esse registro, ou para datas futuras, a compra reserva as datas e o inicio
  depende de abrir o portal. As diarias seguintes continuam comecando pelo portal.

## Identificacao sem alterar o roteador

O script `registrarMacs` existente envia leases DHCP, incluindo aparelhos que
podem estar desconectados. Esse relatorio sozinho NAO inicia uma diaria.
O inicio exige uma requisicao do portal em primeiro plano, pelo IP publico de
um onibus com sincronizacao recente, e um report recente do mesmo MAC/onibus.
Se nao existir report recente desse MAC, aceita o MAC/IP ja cadastrado no mesmo
onibus identificado pela requisicao. Exige IP do aparelho identico ao cadastro;
IP publico sozinho, outro onibus ou report recente conflitante nao bastam.
O portal tenta novamente enquanto aguarda o report (ate cerca de 90 segundos).
Se o captive portal nao abrir automaticamente, o passageiro deve abrir o site.
Nao ha deteccao exata da associacao Wi-Fi com os dados atualmente enviados.

O bypass de 3 minutos serve apenas para pagar. Na confirmacao, um checkout
recente elegivel troca esse prazo pela primeira diaria de 12h ou 24h. Se o
portal estiver fechado, a liberacao continua pelo sync normal do MikroTik.
`payments:reconcile` e a recuperacao do sync tambem corrigem checkouts elegiveis
ja pagos sem diaria, desde que o prazo contado do pagamento ainda esteja valido.
Nunca renovam uma diaria, iniciam a proxima data automaticamente ou encurtam
uma liberacao manual ativa. Nao enviam WhatsApp nessa recuperacao.
Ao abrir o portal, continua possivel iniciar uma diaria disponivel mesmo com
bypass expirado. No segundo dia o passageiro deve abrir o portal novamente.

A liberacao usa os mesmos `status` e `expires_at` do usuario. `syncPagos` continua
recebendo `L:MAC` e `R:MAC`. O bloqueio depende do proximo sync com o servidor:
se a Starlink ficar offline, a remocao pode atrasar, como no fluxo existente.
Mudanca do MAC privado exige recuperar o cadastro; preservar o mesmo MAC por
rede evita que o aparelho seja tratado como um novo passageiro.

## Verificacao local

```sh
php vendor/phpunit/phpunit/phpunit --bootstrap vendor/autoload.php tests/Feature/IntervalPlanTest.php --no-configuration
node tests/interval-plan-js.cjs
php -S 127.0.0.1:8097 -t public tests/Support/interval-preview.php
```

Os testes usam SQLite em memoria e nao chamam gateways. A previa mostra o
portal e `/admin/settings`, com dados descartaveis e pagamentos desabilitados.
Antes de disponibilizar para passageiros, conferir uma compra e uma reconexao
em um onibus real, incluindo a identificacao do IP publico por eventuais proxies.
