-- Verificação em duas etapas (aplicativo autenticador + códigos de recuperação)
ALTER TABLE users
  ADD COLUMN two_factor_secret VARCHAR(255) NULL COMMENT 'chave TOTP criptografada (Crypto)' AFTER password_changed_at,
  ADD COLUMN two_factor_enabled_at DATETIME NULL AFTER two_factor_secret,
  ADD COLUMN two_factor_recovery TEXT NULL COMMENT 'JSON com o SHA-256 dos códigos ainda não usados' AFTER two_factor_enabled_at,
  ADD COLUMN two_factor_last_step BIGINT UNSIGNED NULL COMMENT 'último passo de 30 s aceito (o mesmo código não vale duas vezes)' AFTER two_factor_recovery;
