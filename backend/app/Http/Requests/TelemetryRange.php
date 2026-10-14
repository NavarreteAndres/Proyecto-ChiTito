<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Carbon\CarbonImmutable;
class TelemetryRange extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['from'=>['nullable','date_format:Y-m-d\TH:i:sP'],'to'=>['nullable','date_format:Y-m-d\TH:i:sP','after_or_equal:from'],'per_page'=>'nullable|integer|min:1|max:100','page'=>'nullable|integer|min:1|max:10000']; }
    public function bounds(): array {
        $to=$this->input('to') ? CarbonImmutable::parse($this->input('to'))->utc() : CarbonImmutable::now('UTC');
        $from=$this->input('from') ? CarbonImmutable::parse($this->input('from'))->utc() : $to->subDay();
        if($from->gt($to) || $from->diffInDays($to)>366) abort(422,'El rango debe ser válido y no superar 366 días.');
        return [$from,$to];
    }
}
