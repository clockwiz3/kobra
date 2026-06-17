<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Создание карточки. Доступ уже за middleware auth:sanctum + couple.context;
 * колонка резолвится couple-scoped (findOrFail) в контроллере.
 */
class StoreCardRequest extends FormRequest
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
            'column_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:160'],
            'assignee' => ['nullable', 'in:him,her,together'],
            'description' => ['sometimes', 'nullable', 'string'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'due_end_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * Кросс-полевой инвариант дедлайна: due_end_at допустим только вместе с due_at
     * и не раньше него (диапазон). Одиночная дата — только due_at.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->has('due_at') || $v->errors()->has('due_end_at')) {
                return; // даты уже не прошли базовую валидацию — не парсим
            }
            $end = $this->input('due_end_at');
            if ($end === null || $end === '') {
                return; // одиночная дата либо без дедлайна
            }
            $start = $this->input('due_at');
            if ($start === null || $start === '') {
                $v->errors()->add('due_end_at', 'Конец диапазона можно задать только вместе с началом (due_at).');

                return;
            }
            if (Carbon::parse($end)->lt(Carbon::parse($start))) {
                $v->errors()->add('due_end_at', 'Конец диапазона дедлайна не может быть раньше начала.');
            }
        });
    }
}
