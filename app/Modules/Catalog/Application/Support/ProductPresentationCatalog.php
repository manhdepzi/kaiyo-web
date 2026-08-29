<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Support;

use App\Modules\Catalog\Application\Data\PublicProductImageView;

final class ProductPresentationCatalog
{
    /** @var array{path: string, mime: string, title: string} */
    private const DEMO_VIDEO = [
        'path' => 'videos/demo/product-placeholder.mp4',
        'mime' => 'video/mp4',
        'title' => 'Video minh họa sản phẩm – nội dung tạm thời',
    ];

    /**
     * Static design assets are presentation metadata only. Product identity,
     * publication state and variants remain governed by the Catalog database.
     *
     * @var array<string, list<array{path: string, width: int, height: int}>>
     */
    private const IMAGES = [
        'ong-gio-tron-inox' => [
            ['path' => 'images/products/catalog/ong-gio-tron-inox-1.jpg', 'width' => 500, 'height' => 500],
            ['path' => 'images/products/catalog/ong-gio-tron-inox-2.jpg', 'width' => 500, 'height' => 500],
            ['path' => 'images/products/catalog/ong-gio-tron-inox-3.jpg', 'width' => 500, 'height' => 500],
        ],
        'ong-gio-boc-tam-mgo-tieu-chuan-ei' => [
            ['path' => 'images/products/catalog/ong-gio-mgo-1.jpg', 'width' => 640, 'height' => 480],
            ['path' => 'images/products/catalog/ong-gio-mgo-2.jpg', 'width' => 960, 'height' => 720],
            ['path' => 'images/products/catalog/ong-gio-mgo-3.jpg', 'width' => 640, 'height' => 480],
        ],
        'ong-gio-tron-ton-ma-kem' => [
            ['path' => 'images/products/catalog/ong-gio-tron-ma-kem-1.jpg', 'width' => 500, 'height' => 500],
            ['path' => 'images/products/catalog/ong-gio-tron-ma-kem-2.jpg', 'width' => 500, 'height' => 500],
            ['path' => 'images/products/catalog/ong-gio-tron-ma-kem-3.jpg', 'width' => 500, 'height' => 500],
        ],
        'ong-gio-vuong-bich-tdc' => [
            ['path' => 'images/products/catalog/ong-gio-vuong-tdc-1.jpg', 'width' => 500, 'height' => 500],
            ['path' => 'images/products/catalog/ong-gio-vuong-tdc-2.jpg', 'width' => 500, 'height' => 500],
            ['path' => 'images/products/catalog/ong-gio-vuong-tdc-3.jpg', 'width' => 500, 'height' => 500],
        ],
        'mieng-gio-chan-mua-nan-z' => [
            ['path' => 'images/design/sanpham/Miệng-gió-cánh-chắn-mưa-nan-lá-sách-Nan-Z-tháo-lắp-3-1-1-247x247.webp', 'width' => 247, 'height' => 247],
        ],
        'van-cau-chi-tron' => [
            ['path' => 'images/products/catalog/van-cau-chi-tron-1.jpg', 'width' => 800, 'height' => 600],
            ['path' => 'images/products/catalog/van-cau-chi-tron-2.jpg', 'width' => 800, 'height' => 600],
            ['path' => 'images/products/catalog/van-cau-chi-tron-3.jpg', 'width' => 800, 'height' => 600],
        ],
        'van-chan-lua-dong-co' => [
            ['path' => 'images/products/catalog/van-chan-lua-dong-co-1.jpg', 'width' => 956, 'height' => 1276],
        ],
        'van-chan-lua-fd-tieu-chuan-ei-90' => [
            ['path' => 'images/products/catalog/van-chan-lua-ei90-1.jpg', 'width' => 1200, 'height' => 1218],
            ['path' => 'images/products/catalog/van-chan-lua-ei90-2.jpg', 'width' => 800, 'height' => 600],
            ['path' => 'images/products/catalog/van-chan-lua-ei90-3.jpg', 'width' => 800, 'height' => 600],
        ],
        'van-chan-lua-gan-cau-chi-ky-fd-d-e-120' => [
            ['path' => 'images/products/catalog/van-chan-lua-cau-chi-1.jpg', 'width' => 800, 'height' => 600],
        ],
    ];

    /** @var array<string, array<string, string>> */
    private const SPECIFICATIONS = [
        'ong-gio-tron-inox' => ['Vật liệu' => 'Inox', 'Hình dạng' => 'Tròn', 'Ứng dụng' => 'Cấp, hồi và thải gió'],
        'ong-gio-boc-tam-mgo-tieu-chuan-ei' => ['Cấu tạo' => 'Ống gió bọc tấm MgO', 'Cấp cấu hình' => 'EI30, EI60 hoặc EI90', 'Hình thức' => 'Sản xuất theo hồ sơ dự án'],
        'ong-gio-tron-ton-ma-kem' => ['Vật liệu' => 'Tôn mạ kẽm', 'Hình dạng' => 'Tròn', 'Ứng dụng' => 'Cấp, hồi và thải gió'],
        'ong-gio-vuong-bich-tdc' => ['Vật liệu' => 'Tôn mạ kẽm', 'Hệ bích' => 'TDC', 'Hình thức' => 'Sản xuất theo bản vẽ'],
        'mieng-gio-chan-mua-nan-z' => ['Kiểu nan' => 'Nan Z chắn mưa', 'Vị trí' => 'Lấy gió hoặc thải gió ngoài trời', 'Hình thức' => 'Sản xuất theo kích thước'],
        'van-cau-chi-tron' => ['Cơ cấu' => 'Cầu chì nhiệt', 'Hình dạng' => 'Tròn', 'Hình thức' => 'Sản xuất theo cấu hình'],
        'van-chan-lua-dong-co' => ['Cơ cấu' => 'Động cơ', 'Ứng dụng' => 'Hệ thống thông gió', 'Hình thức' => 'Sản xuất theo bản vẽ'],
        'van-chan-lua-fd-tieu-chuan-ei-90' => ['Dòng sản phẩm' => 'Van chặn lửa FD', 'Cấp cấu hình' => 'EI90', 'Hình thức' => 'Sản xuất theo hồ sơ kỹ thuật'],
        'van-chan-lua-gan-cau-chi-ky-fd-d-e-120' => ['Cơ cấu' => 'Cầu chì nhiệt', 'Cấp cấu hình' => 'EI120', 'Hình thức' => 'Sản xuất theo hồ sơ kỹ thuật'],
    ];

    /** @return list<PublicProductImageView> */
    public function imagesFor(string $slug, string $productName): array
    {
        return array_map(
            fn (array $image): PublicProductImageView => new PublicProductImageView(
                asset($image['path']),
                $productName,
                $image['width'],
                $image['height'],
            ),
            self::IMAGES[$slug] ?? [],
        );
    }

    public function primaryFor(string $slug, string $productName): ?PublicProductImageView
    {
        return $this->imagesFor($slug, $productName)[0] ?? null;
    }

    /** @return array{url: string, mime: string, title: string}|null */
    public function videoFor(string $slug): ?array
    {
        if (! array_key_exists($slug, self::IMAGES)) {
            return null;
        }

        return [
            'url' => asset(self::DEMO_VIDEO['path']),
            'mime' => self::DEMO_VIDEO['mime'],
            'title' => self::DEMO_VIDEO['title'],
        ];
    }

    /** @return array<string, string> */
    public function specificationsFor(string $slug): array
    {
        return self::SPECIFICATIONS[$slug] ?? [];
    }
}
