<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Catalog\Application\Actions\SubmitOwnProductReview;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AccountProductReviewController extends Controller
{
    public function __invoke(Request $request, string $product, SubmitOwnProductReview $reviews): RedirectResponse
    {
        $values = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'min:20', 'max:5000'],
            'expected_version' => ['nullable', 'integer', 'min:0'],
        ]);
        $account = $request->user();
        abort_unless($account instanceof UserAccount, 404);

        try {
            $reviews->execute(
                $account, $product, (int) $values['rating'], (string) $values['title'], (string) $values['body'],
                isset($values['expected_version']) ? (int) $values['expected_version'] : null,
            );
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['review' => 'Chưa thể gửi đánh giá: '.$exception->getMessage()]);
        }

        return back()->with('status', 'Đánh giá đã được gửi và đang chờ kiểm duyệt.');
    }
}
