<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Validação server-side. Regras em string: "required|email|max:160".
 * Devolve os dados já normalizados (telefone só dígitos, placa em maiúsculas,
 * valores monetários em ponto decimal, vazio => null).
 */
final class Validator
{
    /** @return array dados limpos; lança ValidationException com todos os erros */
    public static function validate(array $input, array $rules, array $labels = [], array $messages = []): array
    {
        $clean = [];
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $ruleList = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $label = $labels[$field] ?? self::humanize($field);
            $value = $input[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            $isEmpty = $value === null || $value === '' || $value === [];
            $required = in_array('required', $ruleList, true);

            if ($isEmpty) {
                if ($required) {
                    $errors[$field] = $messages["$field.required"] ?? "Preencha o campo $label.";
                } elseif (in_array('bool', $ruleList, true)) {
                    $clean[$field] = 0;
                } else {
                    $clean[$field] = null;
                }
                continue;
            }

            foreach ($ruleList as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $result = self::apply($name, $arg, $value, $field, $label, $input);
                if (is_string($result)) {
                    $errors[$field] = $messages["$field.$name"] ?? $result;
                    break;
                }
                if (is_array($result)) {
                    $value = $result[0];
                }
            }
            if (!isset($errors[$field])) {
                $clean[$field] = $value;
            }
        }

        if ($errors) {
            throw new ValidationException($errors, count($errors) === 1 ? reset($errors) : 'Confira os campos destacados.');
        }
        return $clean;
    }

    /**
     * @return true|string|array true = ok, string = erro, [valor] = ok com valor normalizado
     */
    private static function apply(string $rule, ?string $arg, mixed $value, string $field, string $label, array $input): true|string|array
    {
        switch ($rule) {
            case 'required':
            case 'nullable':
                return true;

            case 'string':
                return is_string($value) ? true : "$label inválido.";

            case 'max':
                return is_string($value) && mb_strlen($value) > (int) $arg
                    ? "$label deve ter no máximo $arg caracteres." : true;

            case 'min':
                return is_string($value) && mb_strlen($value) < (int) $arg
                    ? "$label deve ter pelo menos $arg caracteres." : true;

            case 'email':
                $v = mb_strtolower((string) $value);
                return filter_var($v, FILTER_VALIDATE_EMAIL) && mb_strlen($v) <= 160
                    ? [$v] : 'Informe um e-mail válido.';

            case 'phone':
                $digits = preg_replace('/\D/', '', (string) $value);
                if (str_starts_with($digits, '55') && strlen($digits) > 11) {
                    $digits = substr($digits, 2);
                }
                return preg_match('/^[1-9]{2}9?\d{8}$/', $digits)
                    ? [$digits] : 'Informe um telefone com DDD, ex.: (11) 91234-5678.';

            case 'plate':
                $plate = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $value));
                return preg_match('/^[A-Z]{3}\d[A-Z0-9]\d{2}$/', $plate)
                    ? [$plate] : 'Placa inválida. Use o formato ABC1D23 ou ABC-1234.';

            case 'integer':
                return filter_var($value, FILTER_VALIDATE_INT) !== false
                    ? [(int) $value] : "$label deve ser um número inteiro.";

            case 'decimal':
                $n = self::parseDecimal($value);
                return $n === null ? "$label deve ser um valor numérico." : [$n];

            case 'minval':
                return (float) $value < (float) $arg ? "$label deve ser no mínimo " . self::fmt((float) $arg) . '.' : true;

            case 'maxval':
                return (float) $value > (float) $arg ? "$label deve ser no máximo " . self::fmt((float) $arg) . '.' : true;

            case 'positive':
                return (float) $value > 0 ? true : "$label deve ser maior que zero.";

            case 'km':
                $digits = preg_replace('/\D/', '', (string) $value);
                return $digits !== '' && (int) $digits <= 3000000 ? [(int) $digits] : 'Informe a quilometragem só com números.';

            case 'year':
                $max = (int) date('Y') + 1;
                return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1950, 'max_range' => $max]]) !== false
                    ? [(int) $value] : "Informe um ano entre 1950 e $max.";

            case 'in':
                return in_array((string) $value, explode(',', (string) $arg), true) ? true : "$label inválido.";

            case 'date':
                $d = \DateTime::createFromFormat('!Y-m-d', (string) $value);
                return $d && $d->format('Y-m-d') === $value ? true : "Informe uma data válida para $label.";

            case 'time':
                return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $value) ? true : "Horário inválido em $label.";

            case 'after_or_equal':
                $other = $input[$arg] ?? null;
                return $other && (string) $value < (string) $other ? "$label não pode ser anterior à data inicial." : true;

            case 'bool':
                return [in_array($value, ['1', 1, true, 'on', 'true'], true) ? 1 : 0];

            case 'confirmed':
                return ($input[$field . '_confirmation'] ?? null) === $value ? true : 'As senhas não conferem.';

            case 'password':
                if (mb_strlen((string) $value) < 8) {
                    return 'A senha deve ter pelo menos 8 caracteres.';
                }
                if (!preg_match('/[A-Za-z]/', (string) $value) || !preg_match('/\d/', (string) $value)) {
                    return 'A senha deve combinar letras e números.';
                }
                return mb_strlen((string) $value) > 72 ? 'A senha deve ter no máximo 72 caracteres.' : true;

            case 'cnpj':
                $digits = preg_replace('/\D/', '', (string) $value);
                return self::validCnpj($digits) ? [$digits] : 'CNPJ inválido.';

            case 'cpf':
                $digits = preg_replace('/\D/', '', (string) $value);
                return self::validCpf($digits) ? [$digits] : 'CPF inválido.';

            case 'document':
                $digits = preg_replace('/\D/', '', (string) $value);
                if (strlen($digits) === 11) {
                    return self::validCpf($digits) ? [$digits] : 'CPF inválido.';
                }
                return self::validCnpj($digits) ? [$digits] : 'Informe um CPF ou CNPJ válido.';

            case 'zip':
                $digits = preg_replace('/\D/', '', (string) $value);
                return strlen($digits) === 8 ? [$digits] : 'CEP inválido.';

            case 'uf':
                $uf = strtoupper((string) $value);
                return preg_match('/^(AC|AL|AP|AM|BA|CE|DF|ES|GO|MA|MT|MS|MG|PA|PB|PR|PE|PI|RJ|RN|RS|RO|RR|SC|SP|SE|TO)$/', $uf)
                    ? [$uf] : 'UF inválida.';

            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', (string) $value)
                    ? true : "Informe um endereço válido (começando com https://) em $label.";

            case 'unique':
                // unique:tabela,coluna[,idIgnorado]
                [$table, $column, $ignore] = array_pad(explode(',', (string) $arg), 3, null);
                $sql = "SELECT COUNT(*) FROM `$table` WHERE `$column` = :v" . ($ignore ? ' AND id <> :ignore' : '');
                $params = ['v' => $value];
                if ($ignore) {
                    $params['ignore'] = (int) $ignore;
                }
                return (int) Database::value($sql, $params) > 0 ? "Já existe um cadastro com este valor de $label." : true;

            case 'exists':
                [$table, $column] = array_pad(explode(',', (string) $arg), 2, 'id');
                return (int) Database::value("SELECT COUNT(*) FROM `$table` WHERE `$column` = :v", ['v' => $value]) > 0
                    ? true : "$label não encontrado.";
        }
        throw new \LogicException("Regra de validação desconhecida: $rule");
    }

    public static function parseDecimal(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $v = str_replace(['R$', ' ', "\u{00A0}"], '', (string) $value);
        if (str_contains($v, ',')) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        }
        return is_numeric($v) ? round((float) $v, 2) : null;
    }

    private static function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
    }

    public static function validCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }
        return true;
    }

    public static function validCnpj(string $cnpj): bool
    {
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }
        $weights = [[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]];
        foreach ([12, 13] as $k => $len) {
            $sum = 0;
            for ($i = 0; $i < $len; $i++) {
                $sum += (int) $cnpj[$i] * $weights[$k][$i];
            }
            $digit = $sum % 11 < 2 ? 0 : 11 - $sum % 11;
            if ((int) $cnpj[$len] !== $digit) {
                return false;
            }
        }
        return true;
    }

    private static function humanize(string $field): string
    {
        return str_replace('_', ' ', $field);
    }
}
