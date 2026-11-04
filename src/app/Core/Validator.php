<?php

namespace App\Core;

/**
 * Validation stricte des données entrantes (formulaire ou query string).
 *
 *   $v = new Validator($request->all());
 *   $rating  = $v->int('rating', 'La note', min: 1, max: 5);
 *   $comment = $v->string('comment', 'Le commentaire', min: 3, max: 1000);
 *   $v->validate();   // lève une ValidationException (422) s'il y a au moins une erreur
 *
 * Chaque méthode renvoie la valeur propre et typée ; une erreur est mémorisée sinon.
 * Principe : on décrit ce qui est AUTORISÉ (liste blanche), tout le reste est refusé.
 */
final class Validator
{
    /** Prénom, nom, ville : lettres (accents compris), espaces, apostrophes et tirets. */
    public const REGEX_NAME = "/^\\p{L}[\\p{L} '\\-]*$/u";

    /** @var array<string, string> */
    private array $errors = [];

    public function __construct(private readonly array $data) {}

    /** Entier strict : filter_var refuse "12abc", "1.5", " 3", "1e3"… contrairement à un cast (int). */
    public function int(string $field, string $label, int $min, int $max, bool $required = true): ?int
    {
        $value = $this->value($field);

        if ($value === '') {
            return $this->missing($field, $label, $required);
        }

        $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);

        if ($int === false) {
            return $this->fail($field, "$label doit être un nombre entier entre $min et $max.");
        }

        return $int;
    }

    /** Chaîne avec longueur min / max (en caractères, mb_strlen) et regex optionnelle. */
    public function string(string $field, string $label, int $min, int $max, ?string $regex = null, bool $required = true): ?string
    {
        $value = $this->value($field);

        if ($value === '') {
            return $this->missing($field, $label, $required);
        }

        if (!mb_check_encoding($value, 'UTF-8')) {
            return $this->fail($field, "$label contient des caractères invalides.");
        }

        $length = mb_strlen($value);
        if ($length < $min || $length > $max) {
            return $this->fail($field, "$label doit faire entre $min et $max caractères.");
        }

        if ($regex !== null && !preg_match($regex, $value)) {
            return $this->fail($field, "$label n'a pas le bon format.");
        }

        return $value;
    }

    public function email(string $field, string $label): ?string
    {
        $value = $this->value($field);

        if ($value === '') {
            return $this->missing($field, $label, true);
        }

        if (mb_strlen($value) > 190 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return $this->fail($field, "$label n'est pas une adresse e-mail valide.");
        }

        return mb_strtolower($value);
    }

    /** Mot de passe : longueur uniquement, et SANS trim (les espaces font partie du mot de passe). */
    public function password(string $field, string $label, int $min, int $max, bool $required = true): ?string
    {
        $value = $this->value($field, trim: false);

        if ($value === '') {
            return $this->missing($field, $label, $required);
        }

        $length = mb_strlen($value);
        if ($length < $min || $length > $max) {
            return $this->fail($field, "$label doit faire entre $min et $max caractères.");
        }

        return $value;
    }

    /** Liste blanche : la valeur doit être EXACTEMENT l'une des valeurs autorisées. */
    public function in(string $field, string $label, array $allowed, bool $required = true): ?string
    {
        $value = $this->value($field);

        if ($value === '') {
            return $this->missing($field, $label, $required);
        }

        if (!in_array($value, $allowed, true)) {
            return $this->fail($field, "$label n'est pas une valeur autorisée.");
        }

        return $value;
    }

    /** Case à cocher : présente => true, absente => false. */
    public function bool(string $field): bool
    {
        return $this->value($field) !== '';
    }

    /** Ajoute une erreur métier calculée dans le contrôleur (ex : confirmation du mot de passe). */
    public function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    /** @throws ValidationException */
    public function validate(): void
    {
        if ($this->errors !== []) {
            throw new ValidationException($this->errors);
        }
    }

    /** Valeur brute du champ, en chaîne. Un tableau (?champ[]=…) est refusé avec un 400. */
    private function value(string $field, bool $trim = true): string
    {
        $value = $this->data[$field] ?? '';

        if (is_array($value)) {
            throw HttpException::badRequest("Le paramètre « $field » doit être une valeur simple.");
        }

        return $trim ? trim((string) $value) : (string) $value;
    }

    private function missing(string $field, string $label, bool $required): null
    {
        if ($required) {
            $this->fail($field, "$label est obligatoire.");
        }

        return null;
    }

    private function fail(string $field, string $message): null
    {
        $this->errors[$field] ??= $message;

        return null;
    }
}
