-- Certificados gerados pela loja no modelo da Dafnis.
-- No curso: o nome do treinamento como sai no certificado, o conteúdo programático do verso e quem assina.
ALTER TABLE courses
  ADD COLUMN cert_name VARCHAR(255) NULL COMMENT 'nome do treinamento no certificado; vazio = código + título' AFTER certificate,
  ADD COLUMN cert_syllabus TEXT NULL COMMENT 'conteúdo programático do verso, um item por linha' AFTER cert_name,
  ADD COLUMN cert_signers VARCHAR(255) NULL COMMENT 'JSON: ids dos signatários (Configurações); vazio = os marcados como padrão' AFTER cert_syllabus;

-- Na matrícula: quando e onde foi a parte prática (cursos semipresenciais).
ALTER TABLE enrollments
  ADD COLUMN practical_done_at DATE NULL AFTER completed_at,
  ADD COLUMN practical_location VARCHAR(160) NULL AFTER practical_done_at;

-- No certificado: o que foi impresso (para gerar de novo e para a página de validação).
ALTER TABLE certificates
  ADD COLUMN data TEXT NULL COMMENT 'JSON: dados impressos no certificado gerado pela loja' AFTER external_url;
