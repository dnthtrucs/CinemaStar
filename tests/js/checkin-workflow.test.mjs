import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const source=fs.readFileSync('resources/js/checkin.js','utf8').replace(/^import .*;\r?\n/gm,'');
function setup({secure=true,startError=null,stopFails=false}={}) {
 const ids=['start-scanner','stop-scanner','scanner-camera','scanner-torch','qr-reader','scanner-message','scan-form','ticket-token','qr-file','scanner-camera-control'];
 const els=Object.fromEntries(ids.map(id=>[id,{hidden:false,disabled:false,value:'',textContent:'',handlers:{},classList:{remove(){},add(){},toggle(){}},addEventListener(type,fn){this.handlers[type]=fn;},setAttribute(){},replaceChildren(){},append(option){if(option.selected)this.value=option.value;}}]));
 const calls=[];let submits=0;let decoded;
 els['scan-form'].requestSubmit=()=>submits++;
 els['qr-reader'].querySelector=()=>({videoWidth:1920,videoHeight:1080,readyState:2,setAttribute(){}});
 class Scanner {
  async start(camera,config,callback){calls.push({camera,config});if(startError)throw startError;this.isScanning=true;decoded=callback;}
  async stop(){this.isScanning=false;if(stopFails)throw Error('Camera closing');}
  getRunningTrackCapabilities(){return {torch:true,focusMode:['continuous']};}
  getRunningTrackSettings(){return {deviceId:'main'};}
  async applyVideoConstraints(){}
  async scanFile(){return 'BK261125TEST1234-0123456789ABCDEF';}
 }
 const window={isSecureContext:secure,addEventListener(){}};
 const document={hidden:false,getElementById:id=>els[id],createElement:tag=>tag==='canvas'?{getContext:()=>({drawImage(){}})}:{}};
 vm.runInNewContext(source,{window,document,navigator:{mediaDevices:{getUserMedia(){},enumerateDevices:async()=>[{kind:'videoinput',deviceId:'main',label:'Back main'},{kind:'videoinput',deviceId:'front',label:'Front'}]}},Html5Qrcode:Scanner,Html5QrcodeSupportedFormats:{CODE_128:1,QR_CODE:2},preferredCameraConfig:id=>id?{deviceId:{exact:id}}:{facingMode:'environment'},cameraErrorMessage:()=> 'Camera chưa được cấp quyền',decodeTicketCanvas:()=>{throw Error('No code');},setTimeout:()=>1,clearTimeout(){},console});
 return {els,calls,submits:()=>submits,decoded:()=>decoded};
}
const live=setup({stopFails:true});await live.els['start-scanner'].handlers.click();
assert.equal(live.calls[0].config.qrbox,undefined);
assert.equal(live.calls[0].config.videoConstraints.width.ideal,1920);
await Promise.all([live.decoded()('  BK261125TEST1234-0123456789ABCDEF '),live.decoded()('duplicate')]);
assert.equal(live.submits(),1,'Camera stop failure must not prevent or duplicate check-in submission');
assert.equal(live.els['ticket-token'].value,'BK261125TEST1234-0123456789ABCDEF');
const denied=setup({startError:{name:'NotAllowedError'}});await denied.els['start-scanner'].handlers.click();
assert.equal(denied.calls.length,1);assert.equal(denied.els['start-scanner'].disabled,false);assert.equal(denied.submits(),0);
const file=setup({secure:false});assert.equal(file.els['start-scanner'].disabled,true);file.els['qr-file'].files=[{}];await file.els['qr-file'].handlers.change();assert.equal(file.submits(),1);
console.log('Camera workflow passed: high-resolution full-frame scan, permission failure, image fallback, stop-error recovery and duplicate prevention');
