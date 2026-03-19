import http from 'k6/http';
import { check, sleep } from 'k6';
import { randomIntBetween } from 'https://jslib.k6.io/k6-utils/1.2.0/index.js';
import { uuidv4 } from 'https://jslib.k6.io/k6-utils/1.4.0/index.js';

const BASE_URL = __ENV.BASE_URL || 'http://localhost';
const LOGIN_EMAIL = __ENV.LOGIN_EMAIL || 'stress@test.com';
const LOGIN_PASSWORD = __ENV.LOGIN_PASSWORD || 'password';

export const options = {
  scenarios: {
    purchase_stress: {
      executor: 'ramping-arrival-rate',
      startRate: 0,
      timeUnit: '1s',
      preAllocatedVUs: 1000,
      maxVUs: 1000,
      stages: [
        { target: 200, duration: '10s' },
        { target: 200, duration: '20s' },
      ],
    },
  },
  thresholds: {
    // Treat 409/422 as successful business logic rejections, only strict 500s are failures
    http_req_failed: ['rate<0.01'], 
    http_req_duration: ['p(95)<2000'],
  },
};

// Authenticate once during setup and share the token across all VUs
export function setup() {
  const loginRes = http.post(`${BASE_URL}/api/login`, JSON.stringify({
    email: LOGIN_EMAIL,
    password: LOGIN_PASSWORD,
  }), {
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
  });

  check(loginRes, {
    'login succeeded': (r) => r.status === 200,
  });

  const body = JSON.parse(loginRes.body);
  return { token: body.access_token };
}

export default function (data) {
  // Target a subset of seats to force contention
  const eventId = 1;
  const seatId = randomIntBetween(1, 100);
  const idempotencyKey = uuidv4();

  const payload = JSON.stringify({
    event_id: eventId,
    seat_id: seatId,
  });

  const params = {
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Idempotency-Key': idempotencyKey,
      'Authorization': `Bearer ${data.token}`,
    },
  };

  const res = http.post(`${BASE_URL}/api/tickets/purchase`, payload, params);

  check(res, {
    'status is 202, 409 or 422': (r) => [202, 409, 422].includes(r.status),
    'no 500 errors': (r) => r.status !== 500,
  });
}
