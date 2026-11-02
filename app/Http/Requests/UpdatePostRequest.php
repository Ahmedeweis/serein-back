<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('sections') && is_string($this->input('sections'))) {
            $decoded = json_decode($this->input('sections'), true);
            if (is_array($decoded)) {
                $merge['sections'] = $decoded;
            }
        }
        if ($this->has('tags') && is_string($this->input('tags'))) {
            $decoded = json_decode($this->input('tags'), true);
            if (is_array($decoded)) {
                $merge['tags'] = $decoded;
            }
        }
        if ($this->has('is_featured')) {
            $merge['is_featured'] = filter_var($this->input('is_featured'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        if ($this->has('isFeatured')) {
            $merge['isFeatured'] = filter_var($this->input('isFeatured'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        if ($this->has('category_en') && !$this->has('category')) {
            $merge['category'] = [
                'en' => $this->input('category_en'),
                'ar' => $this->input('category_ar', $this->input('category_en')),
            ];
        }
        if ($this->has('author_role_en') && !$this->has('authorRole') && !$this->has('author_role')) {
            $merge['authorRole'] = [
                'en' => $this->input('author_role_en'),
                'ar' => $this->input('author_role_ar', ''),
            ];
        }
        if ($this->has('youtubeUrl') && !$this->has('youtube_url')) {
            $merge['youtube_url'] = $this->input('youtubeUrl');
        }
        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        $postId = (int) $this->route('id');

        return [
            'title'              => ['nullable'],
            'title.en'           => ['nullable', 'string', 'max:255'],
            'title.ar'           => ['nullable', 'string', 'max:255'],
            'title_en'           => ['nullable', 'string', 'max:255'],
            'title_ar'           => ['nullable', 'string', 'max:255'],
            'slug'               => ['nullable', 'string', 'max:255', Rule::unique('posts', 'slug')->ignore($postId)],
            'excerpt'            => ['nullable'],
            'excerpt.en'         => ['nullable', 'string'],
            'excerpt.ar'         => ['nullable', 'string'],
            'excerpt_en'         => ['nullable', 'string'],
            'excerpt_ar'         => ['nullable', 'string'],
            'sections'           => ['nullable', 'array'],
            'sections.*.heading_en' => ['nullable', 'string'],
            'sections.*.heading_ar' => ['nullable', 'string'],
            'sections.*.body_en'    => ['nullable', 'string'],
            'sections.*.body_ar'    => ['nullable', 'string'],
            'content'            => ['nullable'],
            'content.en'         => ['nullable', 'string'],
            'content.ar'         => ['nullable', 'string'],
            'content_en'         => ['nullable', 'string'],
            'content_ar'         => ['nullable', 'string'],
            'cover_image'        => ['nullable', 'string'],
            'coverImage'         => ['nullable', 'string'],
            'image'              => ['nullable', 'image', 'max:5120'],
            'youtube_url'        => ['nullable', 'string', 'url', 'regex:/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/i'],
            'youtubeUrl'         => ['nullable', 'string', 'url', 'regex:/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/i'],
            'author'             => ['nullable', 'string', 'max:255'],
            'author_name'        => ['nullable', 'string', 'max:255'],
            'author_avatar'      => ['nullable', 'string'],
            'author_role'        => ['nullable'],
            'authorRole'         => ['nullable'],
            'author_role_en'     => ['nullable', 'string', 'max:255'],
            'author_role_ar'     => ['nullable', 'string', 'max:255'],
            'category'           => ['nullable'],
            'category_id'        => ['nullable', 'integer'],
            'category_en'        => ['nullable', 'string', 'max:255'],
            'category_ar'        => ['nullable', 'string', 'max:255'],
            'is_featured'        => ['nullable', 'boolean'],
            'isFeatured'         => ['nullable', 'boolean'],
            'status'             => ['nullable', 'in:published,draft'],
            'published_at'       => ['nullable', 'date'],
            'publishedAt'        => ['nullable', 'date'],
            'tags'               => ['nullable', 'array'],
            'tags.*'             => ['string'],
            'read_time_minutes'  => ['nullable', 'integer', 'min:1', 'max:120'],
            'readTimeMinutes'    => ['nullable', 'integer', 'min:1', 'max:120'],
        ];
    }

}
