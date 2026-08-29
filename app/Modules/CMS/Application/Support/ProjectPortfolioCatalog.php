<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Support;

use App\Modules\CMS\Application\Data\PublicProjectView;

final class ProjectPortfolioCatalog
{
    /**
     * Curated from Kaiyo's legacy project portfolio. Images are stored locally
     * so public rendering does not depend on the legacy WordPress website.
     *
     * @var list<array{string, string, string, string, string|null, int|null, string|null}>
     */
    private const PROJECTS = [
        ['khu-chung-cu-ket-hop-thuong-mai-dich-vu', 'Khu chung cư kết hợp thương mại dịch vụ', 'featured-2025', 'du-an-1.webp', null, null, 'Ống gió – Van gió'],
        ['nha-may-ict-vina-ii', 'Nhà máy sản xuất thiết bị y tế ICT Vina II', 'featured-2025', 'du-an-2.webp', null, null, 'Ống gió – Van gió'],
        ['du-an-bo-cong-an-tran-binh-trong', 'Dự án 30/81 Trần Bình Trọng – Bộ Công An', 'featured-2025', 'du-an-3.webp', null, null, 'Ống gió'],
        ['nha-may-lioncore-viet-nam', 'Nhà máy Lioncore Việt Nam', 'featured-2025', 'du-an-4.jpg', null, null, 'Ống gió'],
        ['mo-rong-phong-sach-lgitvh', 'Mở rộng phòng sạch cấp độ 10 tầng 2 tòa V2 LGITVH', 'featured-2025', 'du-an-5.jpg', null, 2023, 'Ống gió – Van gió'],
        ['heritage-west-lake', 'Heritage West Lake', 'featured-2025', 'du-an-6.jpg', null, null, 'Ống gió – Van gió'],
        ['du-an-sojitz', 'Dự án SOJITZ', 'featured-2025', 'du-an-7.jpg', null, null, 'Ống gió – Van gió'],

        ['nha-may-seojin-bac-ninh', 'Nhà máy Seojin Bắc Ninh', 'featured-2024', 'seojin-2.jpg', 'KCN Đại Đồng – Từ Sơn – Bắc Ninh', 2020, 'Cung cấp và lắp đặt ống gió'],
        ['nha-may-fenixmark', 'Nhà máy Fenixmark', 'featured-2024', 'fenixmark-nha-may-8.jpg', 'KCN Đình Vũ – Hải An – Hải Phòng', 2021, 'Cung cấp và lắp đặt hệ thống hút'],
        ['nha-thi-dau-tinh-thai-nguyen', 'Nhà thi đấu tỉnh Thái Nguyên', 'featured-2024', 'nha-thi-dau-thai-nguyen.jpg', 'Đội Cấn – Trưng Vương – Thái Nguyên', 2020, 'Cung cấp và lắp đặt ống gió'],
        ['nha-may-bao-bi-thai-duong', 'Nhà máy bao bì Thái Dương', 'featured-2024', 'nha-may-bao-bi-thai-duong.jpg', 'Lạc Đạo – Văn Lâm – Hưng Yên', 2020, 'Làm mát nhà xưởng'],
        ['du-an-nidec-hoa-lac', 'Dự án Nidec Hòa Lạc', 'featured-2024', 'du-an-nidec-hoa-lac.webp', 'KCN Hòa Lạc – Thạch Thất – Hà Nội', 2019, 'Cung cấp và lắp đặt đường ống khí sạch'],
        ['du-an-uy-ban-dan-toc', 'Dự án Ủy ban Dân tộc', 'featured-2024', 'du-an-toa-nha-uy-ban-dan-toc.webp', '349 Đội Cấn – Ba Đình – Hà Nội', 2018, 'Cung cấp ống gió và phụ kiện'],
        ['nha-may-gach-tasa', 'Dự án nhà máy gạch Tasa', 'featured-2024', 'nha-may-gach-tasa.webp', 'KCN Thụy Vân – Việt Trì – Phú Thọ', 2020, 'Cung cấp và lắp đặt hệ thống hút'],
        ['pccc-khu-nam-do', 'Dự án PCCC khu Nam Đô', 'featured-2024', 'du-an-nam-do.webp', '609 Trương Định – Hoàng Mai – Hà Nội', 2021, 'Cung cấp và lắp đặt ống gió'],
        ['nha-may-rocom', 'Dự án nhà máy Rocom', 'featured-2024', 'du-an-rocom-1.webp', 'KCN Yên Phong – Yên Phong – Bắc Ninh', 2021, 'Cung cấp ống gió và phụ kiện'],
        ['benh-vien-phu-san-trung-uong', 'Dự án Bệnh viện Phụ sản Trung ương', 'featured-2024', 'benh-vien-phu-san-tw.webp', '43 Tràng Thi – Hoàn Kiếm – Hà Nội', 2019, 'Cung cấp và lắp đặt đường ống khí sạch'],
        ['nha-may-ja-solar', 'Dự án nhà máy JA Solar', 'featured-2024', 'ja-solar-bac-giang.webp', 'KCN Quang Châu – Việt Yên – Bắc Giang', 2021, 'Cung cấp và lắp đặt ống gió'],
        ['nha-may-goldsun', 'Dự án nhà máy Goldsun', 'featured-2024', 'nha-may-gold-sun-bac-ninh.webp', 'KCN Quế Võ I – Nam Sơn – Bắc Ninh', 2018, 'Cung cấp ống gió và phụ kiện'],
        ['nha-may-giay-ngoc-te', 'Dự án nhà máy giày Ngọc Tề', 'featured-2024', 'ngoc-te-hung-yen.webp', 'Thị trấn Vương – Tiên Lữ – Hưng Yên', 2021, 'Cung cấp ống gió và phụ kiện'],
        ['nha-may-compal', 'Dự án nhà máy Compal', 'featured-2024', 'nha-may-compal-vinh-phuc.webp', 'KCN Bá Thiện – Bình Xuyên – Vĩnh Phúc', 2021, 'Cung cấp và lắp đặt ống gió'],
        ['nha-may-midori', 'Dự án nhà máy Midori', 'featured-2024', 'nha-may-midori-vinh-phuc.webp', 'KCN Khai Quang – Vĩnh Yên – Vĩnh Phúc', 2019, 'Lắp đặt điều hòa trung tâm VRV'],

        ['eway-tech', 'Lắp đặt hệ thống điều hòa không khí cho Công ty TNHH EWAY TECH', 'other', 'eway-tech.jpg', null, null, 'Điều hòa không khí'],
        ['nha-may-may-mac-gia-dung-ha-nam', 'Ống gió EI30, EI45 cho nhà máy sản xuất may mặc, điện tử gia dụng – Hà Nam', 'other', 'nha-may-may-mac-gia-dung-ha-nam.jpg', 'Hà Nam', null, 'Ống gió chống cháy EI30, EI45'],
        ['dich-vu-ky-thuat-bt', 'Cung cấp tấm chống cháy và bông thủy tinh cho Công ty Dịch vụ Kỹ thuật B&T', 'other', 'dich-vu-ky-thuat-bt.jpg', null, null, 'Vật tư chống cháy'],
        ['lap-dat-ong-thong-gio-vien-phu-san', 'Cung cấp, lắp đặt ống thông gió Viện Phụ sản Trung ương', 'other', 'vien-phu-san-trung-uong.jpg', 'Hà Nội', null, 'Ống thông gió'],
        ['xuong-may-van-laack-asia', 'Hệ thống làm mát xưởng may Van Laack Asia', 'other', 'van-laack-asia.jpg', null, null, 'Làm mát nhà xưởng'],
        ['he-thong-tang-ap-cau-thang', 'Hệ thống tăng áp cầu thang', 'other', 'tang-ap-cau-thang.webp', null, null, 'Tăng áp cầu thang'],
        ['cong-trinh-vingroup', 'Cung cấp ống gió cho hệ thống công trình của Tập đoàn Vingroup', 'other', 'vingroup.jpg', null, null, 'Ống gió'],
        ['nha-may-quartz-phu-tho', 'Cung cấp, lắp hệ thống hút bụi nhà máy Quartz Phú Thọ', 'other', 'quartz-phu-tho.jpg', 'Phú Thọ', null, 'Hệ thống hút bụi'],
        ['toyota-hung-yen', 'Lắp đặt thay thế đường ống quạt hút tại Toyota – Hưng Yên', 'other', 'toyota-hung-yen.jpg', 'Hưng Yên', null, 'Đường ống quạt hút'],
        ['thuy-dien-nam-so-1', 'Cung cấp ống gió inox cho công trình thủy điện Nậm So 1', 'other', 'thuy-dien-nam-so-1.jpg', null, null, 'Ống gió inox'],
        ['nha-may-may-thien-son', 'Hệ thống hút khói EI45 cho nhà máy may Thiên Sơn – Hưng Yên', 'other', 'nha-may-may-thien-son.webp', 'Hưng Yên', null, 'Hệ thống hút khói EI45'],
        ['khach-san-may-de-ville', 'Ống gió chống cháy EI45 cho khách sạn May De Ville – Hà Nội', 'other', 'may-de-ville.jpg', 'Hà Nội', null, 'Ống gió chống cháy EI45'],
        ['midori-apparel-vinh-phuc', 'Điều hòa trung tâm Daikin VRV IV – Midori Apparel Vĩnh Phúc', 'other', 'midori-apparel-vinh-phuc.jpg', 'Vĩnh Phúc', null, 'Điều hòa trung tâm VRV'],
        ['toa-nha-88-lo-duc', 'Hệ thống hút khói EI45 cho dự án tòa nhà 88 Lò Đúc', 'other', 'toa-nha-88-lo-duc.jpg', 'Hà Nội', null, 'Hệ thống hút khói EI45'],
        ['xuong-in-bao-bi-goldsun', 'Cung cấp ống gió cho xưởng in và bao bì Goldsun Bắc Ninh', 'other', 'xuong-in-bao-bi-goldsun.jpg', 'Bắc Ninh', null, 'Ống gió'],
        ['vina-dae-bac-giang', 'Ống gió chống cháy EI45 cho công trình Vina Dae – Bắc Giang', 'other', 'vina-dae-bac-giang.jpg', 'Bắc Giang', null, 'Ống gió chống cháy EI45'],
        ['benh-vien-da-chien-ha-noi', 'Sản xuất, cung cấp vật tư cho bệnh viện dã chiến tại Hà Nội', 'other', 'benh-vien-da-chien-ha-noi.jpg', 'Hà Nội', null, 'Ống gió và vật tư'],
        ['chung-cu-ct3-phuc-loi', 'Cung cấp ống gió cho chung cư CT3 Phúc Lợi – Long Biên', 'other', 'chung-cu-ct3-phuc-loi.jpg', 'Long Biên – Hà Nội', null, 'Ống gió'],
        ['cong-trinh-ttc-hung-yen', 'Ống gió EI30, EI45 cho công trình TTC – Hưng Yên', 'other', 'cong-trinh-ttc-hung-yen.jpg', 'Hưng Yên', null, 'Ống gió chống cháy EI30, EI45'],
        ['sj-tech-bac-giang', 'Hệ thống hút khí nóng cho máy CNC – SJ Tech Bắc Giang', 'other', 'sj-tech-bac-giang.jpg', 'Bắc Giang', null, 'Hệ thống hút khí nóng'],
    ];

    /** @return list<PublicProjectView> */
    public function all(): array
    {
        return array_map(
            fn (array $project): PublicProjectView => new PublicProjectView(
                $project[0],
                $project[1],
                $project[2],
                '/images/projects/source/'.$project[3],
                $project[4],
                $project[5],
                $project[6],
            ),
            self::PROJECTS,
        );
    }

    /** @return list<PublicProjectView> */
    public function group(string $group): array
    {
        return array_values(array_filter($this->all(), fn (PublicProjectView $project): bool => $project->group === $group));
    }
}
