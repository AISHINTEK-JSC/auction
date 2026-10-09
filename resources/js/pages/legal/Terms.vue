<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import LegalPage from '@/components/marketplace/LegalPage.vue';
import { privacy } from '@/routes/legal';

defineProps<{
    contactEmail: string;
    updatedAt: string;
}>();

const site = usePage().props.name;
</script>

<template>
    <LegalPage
        title="Điều khoản sử dụng"
        :updated-at="updatedAt"
        current="terms"
    >
        <p>
            {{ site }} là nơi các thành viên đăng tin bán hàng theo hình thức
            đấu giá hoặc giá cố định và liên hệ với nhau để mua bán. Khi đăng ký
            tài khoản hoặc sử dụng trang, bạn đồng ý với các điều khoản dưới
            đây.
        </p>

        <h2>1. Tài khoản</h2>
        <ul>
            <li>
                Bạn phải có đầy đủ năng lực hành vi dân sự theo pháp luật Việt
                Nam để tham gia mua bán.
            </li>
            <li>
                Thông tin đăng ký phải chính xác. Bạn cần xác thực email trước
                khi đăng tin, đặt giá hoặc tham gia quà tặng.
            </li>
            <li>
                Mỗi người chỉ dùng một tài khoản. Mỗi tài khoản có một mã số
                thành viên cố định, không đổi và không chuyển nhượng được.
            </li>
            <li>
                Bạn chịu trách nhiệm giữ kín mật khẩu và về mọi hoạt động diễn
                ra dưới tài khoản của mình.
            </li>
        </ul>

        <h2>2. Đăng tin</h2>
        <ul>
            <li>
                Bạn chỉ đăng bán hàng hoá mình có quyền bán, được phép lưu thông
                theo pháp luật, và mô tả đúng tình trạng thực tế. Hình ảnh phải
                là ảnh của chính món hàng.
            </li>
            <li>
                Không đăng hàng cấm, hàng giả, hàng không rõ nguồn gốc, hay nội
                dung vi phạm pháp luật, thuần phong mỹ tục hoặc quyền của người
                khác.
            </li>
            <li>
                Tin có thể phải chờ kiểm duyệt trước khi hiển thị. Chúng tôi có
                quyền từ chối, ẩn hoặc gỡ tin vi phạm mà không cần báo trước.
            </li>
            <li>
                Tin giá cố định tự hết hạn sau một thời gian; người bán có thể
                gia hạn khi tin sắp hết hạn. Tin nháp chỉ mình bạn nhìn thấy cho
                tới khi bạn bấm đăng.
            </li>
        </ul>

        <h2>3. Đấu giá</h2>
        <ul>
            <li>
                Mỗi lượt đặt giá là một cam kết mua với mức giá đó nếu bạn
                thắng. Hãy đọc kỹ mô tả và hỏi người bán trước khi đặt.
            </li>
            <li>
                Đấu giá xuôi: người trả giá cao nhất khi phiên kết thúc là người
                thắng. Lượt đặt sau phải cao hơn giá hiện tại ít nhất một bước
                giá do người bán đặt. Phiên có thể tự gia hạn khi có lượt đặt
                giá vào phút chót.
            </li>
            <li>
                Đấu giá ngược: giá giảm dần theo thời gian; người đầu tiên chấp
                nhận mức giá đang hiển thị là người thắng.
            </li>
            <li>
                Bạn chỉ huỷ được lượt đặt giá trong những trường hợp trang cho
                phép. Người bán không được tự đặt giá cho tin của mình, và không
                ai được dùng nhiều tài khoản hay thông đồng để đẩy giá.
            </li>
            <li>
                Người bán có thể huỷ phiên trước khi phiên kết thúc; khi đó
                không có người thắng.
            </li>
        </ul>

        <h2>4. Sau khi phiên kết thúc</h2>
        <ul>
            <li>
                Người bán và người thắng nhìn thấy thông tin liên hệ của nhau và
                tự thoả thuận việc thanh toán, giao nhận.
            </li>
            <li>
                {{ site }} không giữ tiền, không vận chuyển hàng và không phải
                là một bên trong giao dịch giữa các thành viên.
            </li>
            <li>
                Nếu người thắng từ bỏ, quyền mua được chuyển cho người trả giá
                kế tiếp (nếu có). Thành viên từ bỏ nhiều lần sẽ bị tạm khoá
                quyền đặt giá trong một thời gian.
            </li>
        </ul>

        <h2>5. Đánh giá, bình luận và báo cáo</h2>
        <ul>
            <li>
                Sau giao dịch, hai bên có thể đánh giá nhau. Đánh giá phải trung
                thực và dựa trên giao dịch thật.
            </li>
            <li>
                Bình luận không được chứa lời lẽ xúc phạm, quấy rối, quảng cáo
                rác hoặc thông tin cá nhân của người khác.
            </li>
            <li>
                Nếu thấy tin vi phạm, hãy dùng nút báo cáo trên trang của tin.
                Không báo cáo sai sự thật để gây khó cho người khác.
            </li>
        </ul>

        <h2>6. Quà tặng</h2>
        <p>
            Các chương trình quà tặng do ban quản trị tổ chức, miễn phí tham
            gia. Mỗi thành viên đăng ký một lần cho mỗi chương trình bằng mã số
            thành viên của mình. Người trúng được chọn ngẫu nhiên; tên và mã số
            của người trúng được công bố trên trang của chương trình. Dùng nhiều
            tài khoản để tăng cơ hội trúng sẽ bị loại.
        </p>

        <h2>7. Hành vi bị cấm</h2>
        <ul>
            <li>Mạo danh người khác hoặc cung cấp thông tin sai lệch.</li>
            <li>
                Dùng công cụ tự động để đăng ký, đăng tin, đặt giá hoặc thu thập
                dữ liệu của trang.
            </li>
            <li>
                Can thiệp, dò quét hoặc tìm cách vượt qua các biện pháp bảo vệ
                của hệ thống.
            </li>
            <li>Lừa đảo hoặc dùng trang cho mục đích trái pháp luật.</li>
        </ul>

        <h2>8. Xử lý vi phạm</h2>
        <p>
            Tuỳ mức độ, chúng tôi có thể ẩn tin, xoá bình luận, tạm khoá quyền
            đặt giá hoặc khoá tài khoản. Thành viên bị khoá có thể gửi yêu cầu
            mở khoá kèm giải trình ngay trên trang thông báo khoá.
        </p>

        <h2>9. Giới hạn trách nhiệm</h2>
        <p>
            Nội dung tin đăng do thành viên tự chịu trách nhiệm. Chúng tôi không
            bảo đảm về chất lượng, nguồn gốc, tính hợp pháp của hàng hoá hay khả
            năng thanh toán của người mua, và không chịu trách nhiệm về thiệt
            hại phát sinh từ giao dịch giữa các thành viên. Trang có thể tạm
            ngừng để bảo trì hoặc do sự cố kỹ thuật; thời gian hiển thị trên
            trang lấy theo giờ Việt Nam.
        </p>

        <h2>10. Thay đổi điều khoản</h2>
        <p>
            Điều khoản có thể được cập nhật. Ngày cập nhật gần nhất được ghi ở
            đầu trang. Việc bạn tiếp tục sử dụng trang sau khi điều khoản thay
            đổi nghĩa là bạn chấp nhận nội dung mới.
        </p>

        <h2>11. Liên hệ</h2>
        <p>
            Mọi thắc mắc về điều khoản, xin gửi về
            <a :href="`mailto:${contactEmail}`">{{ contactEmail }}</a
            >. Cách chúng tôi xử lý thông tin của bạn được nêu trong
            <Link :href="privacy()">Chính sách quyền riêng tư</Link>.
        </p>
    </LegalPage>
</template>
