import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';
import { randomIntBetween } from 'https://jslib.k6.io/k6-utils/1.2.0/index.js';
import { uuidv4 } from 'https://jslib.k6.io/k6-utils/1.4.0/index.js';
import { textSummary } from 'https://jslib.k6.io/k6-summary/0.0.4/index.js';

const BASE_URL = __ENV.BASE_URL || 'http://localhost';
const EVENT_ID = Number(__ENV.EVENT_ID || 1);
const SEAT_COUNT = Number(__ENV.SEAT_COUNT || 100);
const REPORT_DIR = __ENV.K6_REPORT_DIR || '.';

// One bearer token per buyer, written by StressTestSeeder. Sharing a single token
// across every VU would trip the per-user `throttle:60,1` limit and the run would
// measure the rate limiter instead of the concurrency contract.
const TOKENS = JSON.parse(open('./stress-tokens.json'));

const accepted = new Counter('purchase_accepted');
const rejected = new Counter('purchase_rejected');
const throttled = new Counter('purchase_throttled');
const serverErrors = new Counter('purchase_server_errors');
const unexpected = new Counter('purchase_unexpected');
// k6 reports a dial timeout as status 0: the request never reached PHP, so it is a
// limit of the load generator's socket budget, not of the concurrency contract.
const connectionErrors = new Counter('purchase_connection_errors');

// 409 (seat already taken / duplicate key) and 422 (sold out) are the expected outcome
// for every losing buyer, so they must not inflate http_req_failed. Only an unexpected
// status — a 5xx above all — is a real failure of the concurrency contract.
http.setResponseCallback(http.expectedStatuses(202, 409, 422));

export const options = {
  scenarios: {
    thundering_herd: {
      executor: 'ramping-vus',
      startVUs: 0,
      // Ramped rather than started at once: 1,000 simultaneous TCP dials saturate the
      // socket budget before PHP sees them, which would measure the network, not the app.
      stages: [
        { duration: '10s', target: TOKENS.length },
        { duration: '20s', target: TOKENS.length },
      ],
      gracefulRampDown: '15s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    purchase_server_errors: ['count==0'],
    purchase_throttled: ['count==0'],
    purchase_unexpected: ['count==0'],
  },
};

export default function () {
  const token = TOKENS[(__VU - 1) % TOKENS.length];
  const seatId = randomIntBetween(1, SEAT_COUNT);

  const response = http.post(
    `${BASE_URL}/api/tickets/purchase`,
    JSON.stringify({ event_id: EVENT_ID, seat_id: seatId }),
    {
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'Idempotency-Key': uuidv4(),
        Authorization: `Bearer ${token}`,
      },
    }
  );

  if (response.status === 202) {
    accepted.add(1);
  } else if (response.status === 409 || response.status === 422) {
    rejected.add(1);
  } else if (response.status === 429) {
    throttled.add(1);
  } else if (response.status >= 500) {
    serverErrors.add(1);
  } else if (response.status === 0) {
    connectionErrors.add(1);
  } else {
    unexpected.add(1);
  }

  check(response, {
    'seat was sold (202) or contention was rejected (409/422)': (r) => [202, 409, 422].includes(r.status),
    'no server error': (r) => r.status < 500,
  });
}

// Persist the run so the numbers claimed in the README are reproducible artifacts
// rather than screenshots. K6_REPORT_DIR must point at a mounted volume.
export function handleSummary(data) {
  const report = textSummary(data, { indent: ' ', enableColors: false });

  return {
    stdout: report,
    [`${REPORT_DIR}/k6-summary.txt`]: report,
    [`${REPORT_DIR}/k6-summary.json`]: JSON.stringify(data, null, 2),
  };
}
