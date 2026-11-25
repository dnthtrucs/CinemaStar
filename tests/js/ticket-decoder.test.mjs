import assert from 'node:assert/strict';
import fs from 'node:fs';
import { inflateSync } from 'node:zlib';
import { decodeTicketCanvas, cameraErrorMessage, preferredCameraConfig } from '../../resources/js/ticket-decoder.js';
const fixture=JSON.parse(fs.readFileSync(new URL('../fixtures/ticket-barcode.json',import.meta.url),'utf8'));
const pixels=inflateSync(Buffer.from(fixture.rgba,'base64'));
const canvas = (data, width=fixture.width, height=fixture.height) => ({width,height,getContext:()=>({getImageData:()=>({data})})});
assert.ok(fixture.width>280,'Generated barcode is wider than the old camera crop');
assert.equal(decodeTicketCanvas(canvas(pixels)),fixture.expected);
const rotated=Buffer.alloc(pixels.length);
for(let i=0;i<pixels.length;i+=4) pixels.copy(rotated,pixels.length-i-4,i,i+4);
assert.equal(decodeTicketCanvas(canvas(rotated)),fixture.expected);
for (const clockwise of [true,false]) {
    const vertical=Buffer.alloc(pixels.length);
    for(let y=0;y<fixture.height;y++) for(let x=0;x<fixture.width;x++) {
        const target=clockwise ? (x*fixture.height + fixture.height-1-y)*4 : ((fixture.width-1-x)*fixture.height+y)*4;
        pixels.copy(vertical,target,(y*fixture.width+x)*4,(y*fixture.width+x)*4+4);
    }
    assert.equal(decodeTicketCanvas(canvas(vertical,fixture.height,fixture.width)),fixture.expected);
}

assert.match(cameraErrorMessage({name:'NotAllowedError'}),/quyền/);
assert.match(cameraErrorMessage({name:'NotReadableError'}),/ứng dụng khác/);
assert.match(cameraErrorMessage({name:'NotFoundError'}),/Không tìm thấy/);
assert.deepEqual(preferredCameraConfig('rear-main'),{deviceId:{exact:'rear-main'}});
assert.deepEqual(preferredCameraConfig(),{facingMode:'environment'});
console.log('Camera decoder passed: real CinemaStar Code128 PNG, 0/90/180/270-degree barcodes, permission errors and camera selection');
