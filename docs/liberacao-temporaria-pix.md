# Liberação temporária ao copiar o PIX

O prazo continua em **3 minutos**, a partir da autorização no servidor depois
que o código é copiado. O sync do MikroTik precisa receber o MAC para conceder
internet; o portal orienta aguardar até 30 segundos antes de abrir o banco.

Correções:

- Status `connected`/`active` com prazo vencido não é considerado acesso válido.
- Copiar novamente reutiliza um bypass ativo, inclusive na segunda liberação,
  sem estender o prazo ou gastar outra tentativa.
- Permanecem o limite de duas liberações por hora e os bloqueios administrativos.
- Falhas de rede e timeout permitem tentar novamente copiando o mesmo PIX.
- Respostas de um modal antigo não atualizam o PIX novo.
- O código precisa ser copiado antes de solicitar o bypass, evitando fechar o
  navegador cativo antes da cópia. Falha de cópia é informada ao passageiro.
- A operação bloqueia os registros durante a atualização para não substituir
  as horas pagas por uma tentativa atrasada de acesso temporário.

## Publicação

Publicar juntos `app/Http/Controllers/PaymentController.php` e
`public/js/portal.js`. Não é necessária migration. Preservar as correções
anteriores do intervalo que já fazem parte do controller atual.
O portal já usa `filemtime` na URL do JavaScript para atualizar o cache.
Reabrir o portal após a publicação; recarregar o PHP/OPcache se a hospedagem exigir.

## Verificação

```sh
php vendor/phpunit/phpunit/phpunit --bootstrap vendor/autoload.php tests/Feature/TempBypassTest.php tests/Feature/IntervalPlanTest.php --no-configuration
node tests/temp-bypass-js.cjs
node tests/interval-plan-js.cjs
```

Os testes usam banco em memória e bloqueiam chamadas HTTP externas. Cobrem
o bypass, a lista `L:MAC`/`R:MAC` enviada ao roteador e a confirmação de pagamento
dos planos normal e por intervalo. Eles não comprovam conectividade real no
ônibus: após publicar, verificar uma cópia e a abertura do banco em um aparelho
conectado ao Wi-Fi, com dados móveis desligados.
