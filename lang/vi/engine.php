<?php

// Vietnamese messages for the auction engine's error codes (the engine itself answers in Japanese).
// ":0" and ":1" are the values the engine interpolated into its own message.

return [
    'unreachable' => 'Không kết nối được hệ thống đấu giá. Vui lòng thử lại sau.',
    'failed' => 'Hệ thống đấu giá từ chối yêu cầu.',

    'E_99' => 'Dữ liệu không hợp lệ.',
    'E_100' => 'Không có dữ liệu.',
    'E_101' => 'Phiên đấu giá không tồn tại.',
    'E_102' => 'Chưa có lượt đặt giá nào.',
    'E_103' => 'Sản phẩm này đã có phiên đấu giá.',
    'E_104' => 'Phiên đấu giá đã bị huỷ.',
    'E_105' => 'Lượt đặt giá không tồn tại.',
    'E_106' => 'Lượt đặt giá đã bị huỷ.',
    'E_107' => 'Giá đặt phải cao hơn giá hiện tại (:0 đ).',
    'E_108' => 'Giá đặt phải thấp hơn hoặc bằng giá hiện tại (:0 đ).',
    'E_109' => 'Bạn không thể đặt giá cho sản phẩm của chính mình.',
    'E_110' => 'Phiên đấu giá chưa bắt đầu hoặc đã kết thúc.',
    'E_111' => 'Đã có người đặt mức giá này.',
    'E_112' => 'Thời gian kết thúc phải sau thời gian bắt đầu ít nhất 5 phút.',
    'E_113' => 'Bạn đã đặt mức giá này rồi.',
    'E_114' => 'Giá đặt phải chia hết cho bước giá :0 đ.',
    'E_115' => 'Lượt đặt giá không thuộc phiên đang diễn ra.',
    'E_116' => 'Lượt đặt giá không nằm trong danh sách thắng.',
    'E_117' => 'Giá khởi điểm, giá kết thúc và bước giảm không hợp lệ.',
    'E_118' => 'Phiên đấu giá sẽ bắt đầu lúc :0 giờ :1 phút.',
    'E_119' => 'Phiên đấu giá đã kết thúc.',
    'E_120' => 'Giá đặt chưa đạt giá sàn của người bán. Hãy thử một mức giá cao hơn.',
    'E_121' => 'Đã có người đặt mức giá này trước bạn. Hãy đặt giá cao hơn.',
];
