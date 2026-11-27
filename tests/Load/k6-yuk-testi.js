// Eşzamanlı kullanıcı yük testi (PERF-013, PERF-014, PERF-015).
// Çalıştırma (sunucu ve örnek veri hazırken):
//   k6 run -e BASE=http://destek-talebi -e VUS=100 tests/Load/k6-yuk-testi.js
// Her sanal kullanıcı: giriş sayfası → giriş (örnek müşteri hesapları) → Taleplerim → bildirimler → çıkış.
// Yalnız geliştirme ortamında çalıştırılır; örnek veri dışında veri değiştirmez (talep açmaz).
import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE = __ENV.BASE || 'http://destek-talebi';
const VUS = parseInt(__ENV.VUS || '100', 10);
const PASSWORD = __ENV.PASSWORD || 'Destek2026!';
const CUSTOMERS = ['M100001', 'M100002', 'M100003', 'M100004', 'M100005'];

export const options = {
  scenarios: {
    kullanicilar: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '20s', target: VUS }, // kullanıcılar kademeli gelir
        { duration: '40s', target: VUS }, // yük sabit kalır
        { duration: '10s', target: 0 },
      ],
      gracefulRampDown: '10s',
    },
  },
  summaryTrendStats: ['avg', 'med', 'p(90)', 'p(95)', 'max'],
  // Sayfa bazında süre özette ayrı görünsün diye (sınır koymaz, her zaman geçer).
  thresholds: {
    'http_req_duration{sayfa:giris}': ['max>=0'],
    'http_req_duration{sayfa:giris_post}': ['max>=0'],
    'http_req_duration{sayfa:talepler}': ['max>=0'],
    'http_req_duration{sayfa:bildirimler}': ['max>=0'],
    'http_req_duration{sayfa:cikis}': ['max>=0'],
  },
};

function csrf(body) {
  const m = body && body.match(/name="_csrf" value="([^"]+)"/);
  return m ? m[1] : '';
}

export default function () {
  const login = http.get(`${BASE}/giris`, { tags: { sayfa: 'giris' } });
  check(login, { 'giriş sayfası 200': (r) => r.status === 200 });

  const no = CUSTOMERS[(__VU - 1) % CUSTOMERS.length];
  const res = http.post(`${BASE}/giris`, { _csrf: csrf(login.body), customer_no: no, password: PASSWORD }, { tags: { sayfa: 'giris_post' } });
  check(res, { 'giriş başarılı (talepler sayfası)': (r) => r.status === 200 && r.url.indexOf('/talepler') !== -1 });

  const list = http.get(`${BASE}/talepler`, { tags: { sayfa: 'talepler' } });
  check(list, { 'Taleplerim 200': (r) => r.status === 200 });

  const notes = http.get(`${BASE}/bildirimler`, { tags: { sayfa: 'bildirimler' } });
  check(notes, { 'bildirimler 200': (r) => r.status === 200 });

  http.post(`${BASE}/cikis`, { _csrf: csrf(notes.body) }, { tags: { sayfa: 'cikis' } });
  sleep(1);
}
