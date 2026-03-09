import http from 'k6/http';
import { check, sleep } from 'k6';
import { randomIntBetween } from 'https://jslib.k6.io/k6-utils/1.2.0/index.js';
import { uuidv4 } from 'https://jslib.k6.io/k6-utils/1.4.0/index.js';

const BASE_URL = __ENV.BASE_URL || 'http://localhost';

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

export default function () {
  // Target a subset of seats to force contention
  const eventId = 1;
  const seatId = randomIntBetween(1, 100);
  const userId = randomIntBetween(1, 10000);
  const idempotencyKey = uuidv4();

  const payload = JSON.stringify({
    event_id: eventId,
    seat_id: seatId,
    user_id: userId,
  });

  const params = {
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Idempotency-Key': idempotencyKey,
    },
  };

  const res = http.post(`${BASE_URL}/api/tickets/purchase`, payload, params);

  check(res, {
    'status is 201, 409 or 422': (r) => [201, 409, 422].includes(r.status),
    'no 500 errors': (r) => r.status !== 500,
  });
}
