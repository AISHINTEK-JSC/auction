<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import LegalPage from '@/components/marketplace/LegalPage.vue';
import { terms } from '@/routes/legal';

defineProps<{
    contactEmail: string;
    updatedAt: string;
}>();

const site = usePage().props.name;
</script>

<template>
    <LegalPage
        title="Chính sách quyền riêng tư"
        :updated-at="updatedAt"
        current="privacy"
    >
        <p>
            Trang này cho biết {{ site }} thu thập những thông tin nào của bạn,
            dùng vào việc gì và ai được nhìn thấy chúng.
        </p>

        <h2>1. Thông tin chúng tôi thu thập</h2>
        <ul>
            <li>
                <strong>Thông tin tài khoản:</strong> họ tên, email, mật khẩu
                (chỉ lưu dạng đã mã hoá một chiều), và những gì bạn tự bổ sung
                như số điện thoại, địa chỉ, giới tính, ngày sinh, ảnh đại diện
                và địa chỉ nhận hàng.
            </li>
            <li>
                <strong>Nội dung bạn đăng:</strong> tin đăng, hình ảnh, bình
                luận, đánh giá, báo cáo vi phạm.
            </li>
            <li>
                <strong>Hoạt động trên trang:</strong> lượt đặt giá, tin bạn
                theo dõi, tìm kiếm đã lưu, chương trình quà tặng bạn tham gia.
            </li>
            <li>
                <strong>Thông tin kỹ thuật:</strong> địa chỉ IP, loại trình
                duyệt, thời điểm truy cập và cookie phiên đăng nhập.
            </li>
        </ul>

        <h2>2. Chúng tôi dùng thông tin để làm gì</h2>
        <ul>
            <li>Tạo và bảo vệ tài khoản, xác thực email của bạn.</li>
            <li>
                Vận hành việc đăng tin, đấu giá và kết nối người bán với người
                mua.
            </li>
            <li>
                Gửi thông báo trên trang và qua email: bị vượt giá, phiên kết
                thúc, tin sắp hết hạn, có tin mới khớp với tìm kiếm đã lưu,
                chương trình quà tặng mới.
            </li>
            <li>
                Phát hiện gian lận, đăng ký tự động và xử lý vi phạm điều khoản.
            </li>
            <li>Thống kê chung để cải thiện trang.</li>
        </ul>

        <h2>3. Ai nhìn thấy thông tin của bạn</h2>
        <ul>
            <li>
                <strong>Công khai:</strong> tên, mã số thành viên, ảnh đại diện,
                tin đăng, bình luận, đánh giá bạn nhận được và tên của bạn trong
                lịch sử đặt giá.
            </li>
            <li>
                <strong>Thông tin liên hệ</strong> (email, số điện thoại, địa
                chỉ): sau khi phiên đấu giá kết thúc, chỉ người bán và người
                thắng nhìn thấy của nhau; người bán thấy thêm địa chỉ nhận hàng
                của người thắng. Với tin giá cố định đang bán, thành viên đã
                đăng nhập và xác thực email xem được thông tin liên hệ của người
                bán.
            </li>
            <li>
                <strong>Ban quản trị</strong> xem được dữ liệu cần thiết để kiểm
                duyệt và hỗ trợ; các thao tác quản trị đều được ghi nhật ký.
            </li>
        </ul>
        <p>Chúng tôi không bán thông tin cá nhân của bạn cho bên thứ ba.</p>

        <h2>4. Bên thứ ba tham gia xử lý</h2>
        <ul>
            <li>
                <strong>Hệ thống đấu giá</strong> xử lý các lượt đặt giá chỉ
                nhận mã số nội bộ của tài khoản, không nhận tên hay email của
                bạn.
            </li>
            <li>
                <strong>Cloudflare Turnstile</strong> được dùng ở bước đăng ký
                và đăng tin để phân biệt người thật với chương trình tự động.
                Cloudflare nhận địa chỉ IP và một số thông tin về trình duyệt
                của bạn cho mục đích này.
            </li>
            <li>
                <strong>Dịch vụ gửi email</strong> nhận địa chỉ email và nội
                dung thông báo gửi cho bạn.
            </li>
            <li>
                Cơ quan nhà nước có thẩm quyền, khi có yêu cầu hợp lệ theo quy
                định của pháp luật.
            </li>
        </ul>

        <h2>5. Cookie</h2>
        <p>
            Trang dùng cookie cần thiết để giữ phiên đăng nhập, chống giả mạo
            yêu cầu và ghi nhớ giao diện sáng/tối bạn chọn. Chúng tôi không dùng
            cookie quảng cáo.
        </p>

        <h2>6. Lưu trữ và bảo mật</h2>
        <p>
            Dữ liệu được lưu trong thời gian tài khoản còn hoạt động. Kết nối
            tới trang được mã hoá, mật khẩu được lưu dạng băm, và bạn có thể bật
            xác thực hai bước trong phần cài đặt. Không hệ thống nào an toàn
            tuyệt đối; nếu nghi ngờ tài khoản bị lộ, hãy đổi mật khẩu ngay và
            báo cho chúng tôi.
        </p>

        <h2>7. Quyền của bạn</h2>
        <ul>
            <li>Xem và sửa thông tin cá nhân trong phần cài đặt tài khoản.</li>
            <li>
                Xoá tài khoản trong phần cài đặt. Một số dữ liệu gắn với giao
                dịch đã diễn ra (lượt đặt giá, đánh giá) có thể được giữ lại ở
                dạng không còn gắn với thông tin liên hệ của bạn, để bảo đảm
                quyền lợi của bên còn lại.
            </li>
            <li>
                Tắt các thông báo tìm kiếm đã lưu bằng cách xoá tìm kiếm đó.
            </li>
            <li>
                Yêu cầu cung cấp bản sao hoặc xoá dữ liệu bằng cách liên hệ với
                chúng tôi.
            </li>
        </ul>

        <h2>8. Thay đổi chính sách</h2>
        <p>
            Chính sách có thể được cập nhật; ngày cập nhật gần nhất được ghi ở
            đầu trang. Những thay đổi quan trọng sẽ được thông báo trên trang.
        </p>

        <h2>9. Liên hệ</h2>
        <p>
            Câu hỏi và yêu cầu về dữ liệu cá nhân, xin gửi về
            <a :href="`mailto:${contactEmail}`">{{ contactEmail }}</a
            >. Xem thêm <Link :href="terms()">Điều khoản sử dụng</Link>.
        </p>
    </LegalPage>
</template>
