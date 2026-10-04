-- Total de lições do pacote, para calcular o percentual de andamento (o SCORM 1.2 não tem esse campo).
ALTER TABLE course_packages
  ADD COLUMN lesson_count SMALLINT UNSIGNED NULL COMMENT 'lições do curso, quando a ferramenta informa no pacote (Rise 360)' AFTER mastery_score;
