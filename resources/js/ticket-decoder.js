import * as ZXing from 'html5-qrcode/third_party/zxing-js.umd.js';
const hints = new Map([
    [ZXing.DecodeHintType.POSSIBLE_FORMATS, [ZXing.BarcodeFormat.CODE_128, ZXing.BarcodeFormat.QR_CODE]],
    [ZXing.DecodeHintType.TRY_HARDER, true],
]);
const decoder = new ZXing.MultiFormatReader();
export function decodeTicketCanvas(canvas) {
    const bitmap = new ZXing.BinaryBitmap(new ZXing.HybridBinarizer(new ZXing.HTMLCanvasElementLuminanceSource(canvas)));
    return decoder.decode(bitmap, hints).getText();
}
export function cameraErrorMessage(error) {
    const name = error?.name || String(error);
    if (/NotAllowed|PermissionDenied|denied/i.test(name)) return 'Camera chưa được cấp quyền. Mở cài đặt quyền của trang và cho phép Camera; sau đó mở lại trang bằng Safari hoặc Chrome.';
    if (/NotFound|DevicesNotFound/i.test(name)) return 'Không tìm thấy camera. Thử chọn camera khác hoặc chọn ảnh mã vạch.';
    if (/NotReadable|TrackStart|busy/i.test(name)) return 'Camera đang được ứng dụng khác sử dụng. Đóng ứng dụng camera/video rồi thử lại.';
    return 'Chưa mở được camera. Truy cập bằng HTTPS trong Safari/Chrome, cấp quyền Camera hoặc chọn ảnh mã vạch bên dưới.';
}
export function preferredCameraConfig(deviceId) {
    return deviceId ? { deviceId: { exact: deviceId } } : { facingMode: 'environment' };
}
