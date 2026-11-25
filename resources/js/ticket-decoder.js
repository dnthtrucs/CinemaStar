import * as ZXing from 'html5-qrcode/third_party/zxing-js.umd.js';
const hints = new Map([
    [ZXing.DecodeHintType.POSSIBLE_FORMATS, [ZXing.BarcodeFormat.CODE_128, ZXing.BarcodeFormat.QR_CODE]],
    [ZXing.DecodeHintType.TRY_HARDER, true],
]);
const decoder = new ZXing.MultiFormatReader();
export function decodeTicketPixels(rgba, width, height) {
    const luminance = new Uint8ClampedArray(width * height);
    for (let i = 0; i < luminance.length; i++) {
        const at = i * 4;
        luminance[i] = (rgba[at] + 2 * rgba[at + 1] + rgba[at + 2]) / 4;
    }
    // Rotate pixels explicitly. The bundled canvas source mutates the preview
    // when attempting rotation and is unreliable for portrait Code 128 frames.
    const sideways = new Uint8ClampedArray(luminance.length);
    for (let y = 0; y < height; y++) for (let x = 0; x < width; x++) {
        sideways[(width - 1 - x) * height + y] = luminance[y * width + x];
    }
    let lastError;
    const sources = [new ZXing.RGBLuminanceSource(luminance, width, height), new ZXing.RGBLuminanceSource(sideways, height, width)];
    // Scan full frames first, then overlapping bands to exclude nearby text
    // and screen moire without requiring the user to crop the camera preview.
    const regions = [...sources];
    for (const centre of [0.5, 0.35, 0.65]) for (const source of sources) {
        const bandHeight = Math.min(source.getHeight(), Math.max(48, Math.round(source.getHeight() * 0.16)));
        const top = Math.max(0, Math.min(source.getHeight() - bandHeight, Math.round(source.getHeight() * centre - bandHeight / 2)));
        regions.push(source.crop(0, top, source.getWidth(), bandHeight));
        for (const fraction of [0.85, 0.75]) {
            const bandWidth = Math.round(source.getWidth() * fraction);
            regions.push(source.crop(Math.round((source.getWidth() - bandWidth) / 2), top, bandWidth, bandHeight));
        }
    }
    for (const source of regions) for (const Binarizer of [ZXing.HybridBinarizer, ZXing.GlobalHistogramBinarizer]) {
        try {
            return decoder.decode(new ZXing.BinaryBitmap(new Binarizer(source)), hints).getText();
        } catch (error) { lastError = error; }
    }
    throw lastError;
}
export function decodeTicketCanvas(canvas) {
    const pixels = canvas.getContext('2d', { willReadFrequently: true }).getImageData(0, 0, canvas.width, canvas.height).data;
    return decodeTicketPixels(pixels, canvas.width, canvas.height);
}
export async function decodeTicketImage(file) {
    const url = URL.createObjectURL(file);
    try {
        const image = new Image();
        image.src = url;
        await image.decode();
        const scale = Math.min(1, 2200 / Math.max(image.naturalWidth, image.naturalHeight));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(image.naturalWidth * scale);
        canvas.height = Math.round(image.naturalHeight * scale);
        canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
        return decodeTicketCanvas(canvas);
    } finally { URL.revokeObjectURL(url); }
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
