-- Dados extras que a NR exige no certificado, informados ao registrar a prática. Hoje, a NR-33 (item 33.6.5):
-- tipo de espaço confinado, atividades desenvolvidas e anuência do responsável técnico (item 33.3.2).
ALTER TABLE enrollments
  ADD COLUMN cert_details TEXT NULL COMMENT 'JSON: dados extras do certificado exigidos pela NR (ex.: NR-33)' AFTER practical_location;

-- NR-33: a parte prática de no mínimo 50% da carga horária é obrigatória (Anexo III, item 1.2), e não só
-- recomendada. Só troca o texto que ainda está no padrão importado ("Recomenda-se ...").
UPDATE courses
   SET practical_hours = CONCAT(FLOOR(hours / 2), ' h no mínimo, metade da carga horária, conforme a NR-33')
 WHERE nr_number = 33 AND practical_required = 1 AND practical_hours LIKE 'Recomenda%';
