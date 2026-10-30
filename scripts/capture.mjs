import { chromium } from 'playwright';
import { mkdir, writeFile } from 'node:fs/promises';
const base = process.env.PRANATA_CAPTURE_URL || 'http://127.0.0.1:8000';
await mkdir('public/images/screenshots', { recursive: true });
await mkdir('artifacts', { recursive: true });
let ready = false;
for (let attempt=0; attempt<40; attempt++) {
    try { if ((await fetch(`${base}/up`)).ok) { ready=true; break; } } catch {}
    await new Promise(resolve=>setTimeout(resolve,500));
}
if (!ready) throw new Error('PRANATA preview server did not start');
const browser = await chromium.launch({headless:true});
const context = await browser.newContext({viewport:{width:1440,height:1080},deviceScaleFactor:1});
const page = await context.newPage();
const errors=[];
page.on('pageerror',error=>errors.push(error.message));
async function open(path) {
    const response=await page.goto(path.startsWith('http')?path:`${base}${path}`,{waitUntil:'networkidle'});
    if (!response || response.status()!==200) throw new Error(`HTTP ${response?.status()} on ${path}`);
}
async function loadImages() {
    for (const image of await page.locator('img').all()) { await image.scrollIntoViewIfNeeded(); await image.evaluate(img=>img.decode()); }
    await page.evaluate(()=>window.scrollTo(0,0));
}
try {
    await open('/demo');
    await page.getByRole('heading',{name:'Dashboard eksekutif'}).waitFor();
    await page.screenshot({path:'public/images/screenshots/dashboard.png'});
    await open('/demo/compliance');
    await page.getByRole('heading',{name:'Kalender kepatuhan'}).waitFor();
    await page.screenshot({path:'public/images/screenshots/compliance.png'});
    await open('/demo/audits');
    const auditUrl=await page.locator('a[href*="/demo/audits/"]').first().getAttribute('href');
    if (!auditUrl) throw new Error('No seeded audit available');
    await open(auditUrl);
    await page.getByRole('heading',{name:'Kertas kerja audit'}).waitFor();
    await page.screenshot({path:'public/images/screenshots/audit.png'});
    for (const path of ['/demo/risks','/demo/obligations','/demo/audit-actions']) await open(path);
    await open('/'); await loadImages();
    await page.screenshot({path:'artifacts/landing-desktop.png',fullPage:true});
    if (await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth+1)) throw new Error('Desktop landing horizontal overflow');
    await page.setViewportSize({width:390,height:844});
    await open('/'); await loadImages();
    await page.screenshot({path:'artifacts/landing-mobile.png',fullPage:true});
    if (await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth+1)) throw new Error('Mobile landing horizontal overflow');
    await open('/demo'); await page.screenshot({path:'artifacts/dashboard-mobile.png',fullPage:true});
    if (await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth+1)) throw new Error('Mobile dashboard horizontal overflow');
    if (errors.length) throw new Error(errors.join('\n'));
    await writeFile('artifacts/browser-report.json',JSON.stringify({desktop:'1440x1080',mobile:'390x844',pagesChecked:8,pageErrors:errors,screenshots:['dashboard.png','compliance.png','audit.png']},null,2));
    console.log('Browser checks passed: demo pages, real screenshots, landing desktop/mobile, no horizontal overflow.');
} catch(error) { await page.screenshot({path:'artifacts/browser-failure.png',fullPage:true}); throw error; }
finally { await browser.close(); }
