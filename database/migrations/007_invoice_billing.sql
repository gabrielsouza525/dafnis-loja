-- Fatura mensal: clientes autorizados pela equipe compram sem pagar na hora. O pedido fica "faturado"
-- (acesso liberado na hora) e é cobrado junto com os outros do mês, na página Faturamento do painel.
-- A autorização é por conta e vale só para o CNPJ informado aqui, para ninguém faturar em nome de outra empresa.
ALTER TABLE users
  ADD COLUMN invoice_cnpj VARCHAR(14) NULL COMMENT 'CNPJ que esta conta pode faturar (fatura mensal); vazio = sem fatura' AFTER document;

ALTER TABLE orders
  MODIFY COLUMN status ENUM('pending','invoiced','paid','cancelled','refunded') NOT NULL DEFAULT 'pending',
  MODIFY COLUMN payment_method ENUM('pix','card','boleto','invoice') NOT NULL DEFAULT 'pix',
  ADD COLUMN invoiced_at DATETIME NULL COMMENT 'pedido faturado: acesso liberado, pagamento no fechamento do mês' AFTER paid_at,
  ADD KEY idx_orders_billing (buyer_document, created_at);
