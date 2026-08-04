<?php

namespace App\Http\Requests\Tenant;

use App\Modules\RentalEngine\Domain\AllocationStrategy;
use App\Modules\RentalEngine\Domain\BookingStrategy;
use App\Modules\RentalEngine\Domain\RentalModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'regular' => ['nullable', 'array:timezone,currency,business_name,operating_hours,default_language,date_format,time_format'],
            'regular.timezone' => ['nullable', 'timezone'],
            'regular.currency' => ['nullable', 'string', 'size:3'],
            'regular.business_name' => ['nullable', 'string', 'max:150'],
            'regular.operating_hours' => ['nullable', 'array'],
            'regular.default_language' => ['nullable', 'string', 'max:10'],
            'regular.date_format' => ['nullable', 'string', 'max:30'],
            'regular.time_format' => ['nullable', 'string', 'max:30'],

            'branding' => ['nullable', 'array:primary_color,secondary_color,font,logo_url,favicon_url,invoice_logo_url'],
            'branding.logo_url' => ['prohibited'],
            'branding.favicon_url' => ['prohibited'],
            'branding.invoice_logo_url' => ['prohibited'],
            'branding.primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'branding.secondary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'branding.font' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9 ,+-]+$/'],

            'public' => ['nullable', 'array:tagline,description,phone,whatsapp,email,social_media,features,seo_title,seo_description'],
            'public.tagline' => ['nullable', 'string', 'max:200'],
            'public.description' => ['nullable', 'string', 'max:2000'],
            'public.phone' => ['nullable', 'string', 'max:30'],
            'public.whatsapp' => ['nullable', 'string', 'max:30'],
            'public.email' => ['nullable', 'email', 'max:150'],
            'public.social_media' => ['nullable', 'array:instagram,facebook,tiktok,youtube'],
            'public.social_media.*' => ['nullable', 'url:http,https', 'max:2048'],
            'public.features' => ['nullable', 'array:guest_checkout,online_payment,blog,reviews'],
            'public.features.*' => ['boolean'],
            'public.seo_title' => ['nullable', 'string', 'max:200'],
            'public.seo_description' => ['nullable', 'string', 'max:1000'],

            'homepage' => ['nullable', 'array:hero_title,hero_subtitle,cta_label,cta_url,how_to_book,testimonials,faq'],
            'homepage.hero_title' => ['nullable', 'string', 'max:200'],
            'homepage.hero_subtitle' => ['nullable', 'string', 'max:500'],
            'homepage.cta_label' => ['nullable', 'string', 'max:100'],
            'homepage.cta_url' => ['nullable', 'string', 'max:2048'],
            'homepage.how_to_book' => ['nullable', 'array', 'max:10'],
            'homepage.how_to_book.*' => ['array:title,description'],
            'homepage.how_to_book.*.title' => ['required', 'string', 'max:150'],
            'homepage.how_to_book.*.description' => ['nullable', 'string', 'max:500'],
            'homepage.testimonials' => ['nullable', 'array', 'max:20'],
            'homepage.testimonials.*' => ['array:name,quote,rating'],
            'homepage.testimonials.*.name' => ['required', 'string', 'max:150'],
            'homepage.testimonials.*.quote' => ['required', 'string', 'max:1000'],
            'homepage.testimonials.*.rating' => ['nullable', 'integer', 'between:1,5'],
            'homepage.faq' => ['nullable', 'array', 'max:30'],
            'homepage.faq.*' => ['array:question,answer'],
            'homepage.faq.*.question' => ['required', 'string', 'max:300'],
            'homepage.faq.*.answer' => ['required', 'string', 'max:2000'],

            'branch' => ['nullable', 'array:name,email,phone,address,latitude,longitude,is_active,logo_url'],
            'branch.name' => ['nullable', 'string', 'max:150'],
            'branch.email' => ['nullable', 'email', 'max:150'],
            'branch.phone' => ['nullable', 'string', 'max:30'],
            'branch.address' => ['nullable', 'string'],
            'branch.latitude' => ['nullable', 'numeric'],
            'branch.longitude' => ['nullable', 'numeric'],
            'branch.is_active' => ['nullable', 'boolean'],
            'branch.logo_url' => ['prohibited'],

            'rental_engine' => ['nullable', 'array:rental_model,booking_strategy,allocation_strategy,slot_duration_minutes,enable_waiting_list,allow_walk_in,allow_online_booking,allow_extend_booking,realtime_availability,auto_reminder,auto_cancel_unpaid,auto_cancel_minutes'],
            'rental_engine.rental_model' => ['nullable', Rule::enum(RentalModel::class)],
            'rental_engine.booking_strategy' => ['nullable', Rule::enum(BookingStrategy::class)],
            'rental_engine.allocation_strategy' => ['nullable', Rule::enum(AllocationStrategy::class)],
            'rental_engine.slot_duration_minutes' => ['nullable', 'integer', 'min:15', 'max:1440'],
            'rental_engine.enable_waiting_list' => ['nullable', 'boolean'],
            'rental_engine.allow_walk_in' => ['nullable', 'boolean'],
            'rental_engine.allow_online_booking' => ['nullable', 'boolean'],
            'rental_engine.allow_extend_booking' => ['nullable', 'boolean'],
            'rental_engine.realtime_availability' => ['nullable', 'boolean'],
            'rental_engine.auto_reminder' => ['nullable', 'boolean'],
            'rental_engine.auto_cancel_unpaid' => ['nullable', 'boolean'],
            'rental_engine.auto_cancel_minutes' => [
                'nullable',
                'integer',
                'min:5',
                'required_if:rental_engine.auto_cancel_unpaid,true',
            ],
        ];
    }
}
