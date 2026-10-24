---
marp: true
size: 4:3
paginate: true
---

# Project 1 Plan: Aquarium Tank API

**Submission Deadline:** October 23, 2026.
**Personal target:** October 23, 2026.
**Actual implementation and verification:** October 23, 2026.

---

## Planned API Endpoints


| # | Endpoint | Description | Bearer | Implemented On |
|---|---|---|:---:|---|
| 1 | GET /tanks | List tanks | | 2026-10-23 |
| 2 | GET /tanks/{id} | Get one tank | | 2026-10-23 |
| 3 | POST /tanks | Create a tank | * | 2026-10-23 |
| 4 | PUT /tanks/{id} | Update name and gallons | | 2026-10-23 |
| 5 | DELETE /tanks/{id} | Delete tank and child records | * | 2026-10-23 |
| 6 | GET /tanks/{id}/readings | List water readings | | 2026-10-23 |
| 7 | POST /readings | Log a water reading | * | 2026-10-23 |
| 8 | DELETE /readings/{id} | Delete a reading | | 2026-10-23 |
| 9 | GET /tanks/{id}/plants | List plants | | 2026-10-23 |
| 10 | POST /plants | Add a plant | | 2026-10-23 |

---

## Milestone 1: API and Database Design

1. **What:** Confirm the 10 aquarium endpoints and design tanks, readings, and plants.
2. **When (Plan):** October 23, 2026.
3. **When (Actual):** October 23, 2026.
4. **Comment:** Kept the endpoints and three protected operations from the supplied aquarium plan.

---

## Milestone 2: PHP/MySQL Implementation and Tests

1. **What:** Implement the API and test all endpoints with cURL and the HTML/JavaScript client.
2. **When (Plan):** October 23, 2026.
3. **When (Actual):** October 23, 2026.
4. **Comment:** Verified with Oracle MySQL 8.4; cURL tests passed on both server configurations and browser tests passed through NGINX.

---

## Milestone 3: Tutorial and NGINX Deployment

1. **What:** Deploy with NGINX/PHP-FPM, export both Marp decks to PDF, capture a real screenshot, and package files.
2. **When (Plan):** October 23, 2026.
3. **When (Actual):** October 23, 2026.
4. **Comment:** Local NGINX uses port 8088.
