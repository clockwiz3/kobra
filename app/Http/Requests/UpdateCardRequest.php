<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Обновление параметров карточки (частичное). Доступ за middleware auth:sanctum + couple.context;
 * карточка резолвится couple-scoped (findOrFail) в контроллере.
 */
class UpdateCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string'],
            'assignee' => ['sometimes', 'in:him,her,together'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'due_end_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * Кросс-полевой инвариант диапазона — сверяем только когда ОБЕ границы пришли в запросе.
     * Если due_at не передан (остаётся из карточки), проверку выполнит бэкстоп
     * CardService::assertValidRange по сохранённому значению.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->has('due_at') || $v->errors()->has('due_end_at')) {
                return;
            }
            $end = $this->input('due_end_at');
            if ($end === null || $end === '') {
                return;
            }
            if (! $this->has('due_at')) {
                return; // начало берётся из существующей карточки → проверит сервис
            }
            $start = $this->input('due_at');
            if ($start === null || $start === '') {
                $v->errors()->add('due_end_at', 'Нельзя очистить начало дедлайна, оставив конец диапазона.');

                return;
            }
            if (Carbon::parse($end)->lt(Carbon::parse($start))) {
                $v->errors()->add('due_end_at', 'Конец диапазона дедлайна не может быть раньше начала.');
            }
        });
    }
}
