import vm from 'node:vm';
import fs from 'node:fs';
import assert from 'node:assert/strict';
const handlers={};let stored=[];let removed=[];let networkResult={source:'network'};let failed=false;
const self={location:{origin:'https://cinema.test'},addEventListener:(type,fn)=>handlers[type]=fn,skipWaiting(){},clients:{claim(){}}};
const caches={open:async()=>({addAll:async assets=>{stored=assets;}}),keys:async()=>['cinemastar-public-old','another-app'],delete:async key=>removed.push(key),match:async key=>({source:'cache',key})};
vm.runInNewContext(fs.readFileSync('public/sw.js','utf8'),{self,caches,URL,fetch:async()=>{if(failed)throw Error('offline');return networkResult;}});
let wait;handlers.install({waitUntil:p=>wait=p});await wait;
assert.deepEqual([...stored],['/offline.html','/images/app-icon-192.png','/images/app-icon-512.png']);
handlers.activate({waitUntil:p=>wait=p});await wait;assert.deepEqual(removed,['cinemastar-public-old']);
for(const path of ['/bookings/42','/payments/42/sepay','/profile']) {
 let response;handlers.fetch({request:{url:'https://cinema.test'+path,method:'GET',mode:'navigate'},respondWith:p=>response=p});
 assert.equal(await response,networkResult);
 failed=true;handlers.fetch({request:{url:'https://cinema.test'+path,method:'GET',mode:'navigate'},respondWith:p=>response=p});
 assert.equal((await response).key,'/offline.html');failed=false;
}
let intercepted=false;handlers.fetch({request:{url:'https://cinema.test/notifications/feed',method:'GET',mode:'cors'},respondWith:()=>intercepted=true});assert.equal(intercepted,false);
handlers.fetch({request:{url:'https://cinema.test/payment',method:'POST',mode:'cors'},respondWith:()=>intercepted=true});assert.equal(intercepted,false);
console.log('PWA checks passed: public-only cache, isolated cleanup, private pages network-only, offline fallback, API and POST untouched');
