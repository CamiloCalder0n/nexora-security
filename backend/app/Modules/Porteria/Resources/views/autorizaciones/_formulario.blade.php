{{-- Campos compartidos por crear y editar: visitante y ventana de validez. --}}
@php($formato = 'Y-m-d\TH:i')
<div class="row">
    <div class="col-md-6">
        <x-campo name="visitante_nombre" label="Nombre del visitante" :value="$autorizacion?->visitante_nombre" maxlength="120" requerido />
    </div>
    <div class="col-md-3">
        <x-campo name="visitante_documento" label="Documento" :value="$autorizacion?->visitante_documento" maxlength="20" requerido />
    </div>
    <div class="col-md-3">
        <x-campo name="placa" label="Placa" :value="$autorizacion?->placa" maxlength="10" ayuda="Opcional, si llega en vehículo." class="text-uppercase" />
    </div>
    <div class="col-md-6">
        <x-campo type="datetime-local" name="valida_desde" label="Válida desde" :value="$autorizacion?->valida_desde?->format($formato)" requerido />
    </div>
    <div class="col-md-6">
        <x-campo type="datetime-local" name="valida_hasta" label="Válida hasta" :value="$autorizacion?->valida_hasta?->format($formato)" ayuda="Máximo {{ \App\Modules\Porteria\Application\AutorizacionService::MAX_DIAS }} días después del inicio." requerido />
    </div>
</div>
