-- Centro de custo (departamento) do participante, informado pela empresa ao indicar quem faz o treinamento.
-- Sai no demonstrativo do Faturamento enviado à empresa.
ALTER TABLE enrollments
  ADD COLUMN cost_center VARCHAR(80) NULL COMMENT 'centro de custo / departamento do participante na empresa compradora' AFTER participant_document;
