<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\CRM\Application\Actions\CapturePublicContact;
use App\Modules\CRM\Application\Data\PublicContactCommand;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PublicContactController extends Controller
{
    public function show(): View
    {
        return view('public.contact');
    }

    public function store(Request $request, CapturePublicContact $capture): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:200'],
            'company_name' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email:rfc', 'max:254', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^(?:\+?[0-9][0-9\s().-]{7,19})$/', 'required_without:email'],
            'topic' => ['required', 'in:product,quotation,project,support,other'],
            'message' => ['required', 'string', 'min:20', 'max:4000'],
            'operation_key' => ['required', 'string', 'max:100'],
            'privacy_accepted' => ['accepted'],
            'website' => ['nullable', 'string', 'max:0'],
        ]);

        try {
            $capture->execute(new PublicContactCommand(
                name: (string) $validated['name'],
                companyName: $this->optional($validated['company_name'] ?? null),
                email: $this->optional($validated['email'] ?? null),
                phone: $this->optional($validated['phone'] ?? null),
                topic: (string) $validated['topic'],
                message: (string) $validated['message'],
                operationKey: (string) $validated['operation_key'],
                abuseKey: hash('sha256', (string) $request->ip()."\0".(string) $request->userAgent()),
            ));
        } catch (DomainException $exception) {
            report($exception);

            return back()->withInput()->withErrors([
                'contact' => 'Chưa thể gửi yêu cầu lúc này. Vui lòng kiểm tra thông tin hoặc thử lại sau.',
            ]);
        }

        return to_route('public.contact')->with('status', 'Yêu cầu đã được ghi nhận. Đội ngũ Kaiyo sẽ xử lý theo quy trình nội bộ.');
    }

    private function optional(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
