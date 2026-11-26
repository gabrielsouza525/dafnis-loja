-- Planilha de medição do Faturamento (o formato que a empresa recebe junto com a nota):
-- a função (cargo) de cada participante, informada pela empresa junto com o centro de custo,
-- e a sigla de cada treinamento na planilha (ex.: NR10SEP). Sem sigla, a planilha usa "NR10".
ALTER TABLE enrollments
  ADD COLUMN job_title VARCHAR(100) NULL COMMENT 'função (cargo) do participante na empresa compradora' AFTER participant_document;

ALTER TABLE courses
  ADD COLUMN billing_code VARCHAR(30) NULL COMMENT 'sigla do treinamento na planilha de medição, ex.: NR10SEP' AFTER code;
