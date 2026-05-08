# Afiação Almeida

Sistema web completo para gestão de afiação de equipamentos de manicure.

Estrutura baseada no prompt do projeto.


Onde parei
Ótimo, vou implementar tudo agora. Deixa eu criar o plano e executar em lotes paralelos.

Update Todos

functions.php: CSRF, cache getConfig, helper paginação

auth.php: autenticação real via banco de dados

criar_tabelas.php: seed admin + colunas endereço cliente

admin.js: CSRF injection, spinner submit, reset pageshow

admin.css: FAB mobile, estilos paginação

header.php: meta CSRF, busca global no topbar

footer.php: botão FAB mobile

ordens.php: status inline, money-mask, paginação, verifyCsrf

clientes.php: observacoes, endereço+CEP, data-phone, paginação, verifyCsrf

entregas/equipamentos/financeiro/configuracoes: verifyCsrf + data-phone

Remover require_once helpers/util dos 7 arquivos