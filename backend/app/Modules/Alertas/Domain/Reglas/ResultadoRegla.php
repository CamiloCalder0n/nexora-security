<?php

namespace App\Modules\Alertas\Domain\Reglas;

final readonly class ResultadoRegla
{
    private function __construct(
        public bool $decide,
        public bool $explicado,
        public ?int $registroId = null,
        public ?string $motivo = null,
        public ?int $prioridad = null,
    ) {}

    /** Un registro de portería explica el evento: no hay alerta. */
    public static function explicado(int $registroId): self
    {
        return new self(decide: true, explicado: true, registroId: $registroId);
    }

    public static function alerta(string $motivo, int $prioridad): self
    {
        return new self(decide: true, explicado: false, motivo: $motivo, prioridad: $prioridad);
    }

    /** La regla no tiene nada que decir; el motor sigue con la siguiente. */
    public static function sinDecision(): self
    {
        return new self(decide: false, explicado: false);
    }
}
