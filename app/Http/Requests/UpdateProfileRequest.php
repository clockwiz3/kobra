<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Обновление профиля текущего юзера (частичное): отображаемое имя и/или «внешний вид» в задачах
 * (appearance — рамка карточки). Identity-слой: за auth:sanctum, ВНЕ couple.context (per-user, не shard).
 * appearance.card присылается ЦЕЛИКОМ (пикер отдаёт полный объект) — сервис заменяет его атомарно,
 * чтобы при переключении solid↔gradient не оставались протухшие ключи paint.
 */
class UpdateProfileRequest extends FormRequest
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
        $hex = 'regex:/^#[0-9a-fA-F]{6}$/';

        return [
            'name' => ['sometimes', 'string', 'max:50'],

            'appearance' => ['sometimes', 'array'],
            'appearance.card' => ['required_with:appearance', 'array'],

            'appearance.card.paint' => ['required_with:appearance.card', 'array'],
            'appearance.card.paint.kind' => ['required_with:appearance.card.paint', 'in:solid,gradient'],
            // solid
            'appearance.card.paint.color' => ['required_if:appearance.card.paint.kind,solid', 'nullable', 'string', $hex],
            // gradient
            'appearance.card.paint.from' => ['required_if:appearance.card.paint.kind,gradient', 'nullable', 'string', $hex],
            'appearance.card.paint.to' => ['required_if:appearance.card.paint.kind,gradient', 'nullable', 'string', $hex],
            'appearance.card.paint.angle' => ['sometimes', 'nullable', 'integer', 'between:0,360'],

            'appearance.card.style' => ['required_with:appearance.card', 'in:solid,dashed,dotted'],
            'appearance.card.width' => ['required_with:appearance.card', 'integer', 'between:1,3'],
            'appearance.card.animated' => ['required_with:appearance.card', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.max' => 'Имя не длиннее 50 символов.',
            'appearance.card.paint.color.regex' => 'Цвет в формате #RRGGBB.',
            'appearance.card.paint.from.regex' => 'Цвет градиента в формате #RRGGBB.',
            'appearance.card.paint.to.regex' => 'Цвет градиента в формате #RRGGBB.',
            'appearance.card.style.in' => 'Стиль рамки: solid, dashed или dotted.',
            'appearance.card.width.between' => 'Толщина рамки от 1 до 3.',
        ];
    }
}
