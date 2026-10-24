#!/usr/bin/env bash
set -euo pipefail
BASE_URL="${BASE_URL:-http://localhost:8080}"
: "${API_TOKEN:?Set API_TOKEN to the same value as the server}"
TANK_ID=''
request() {
    local expected="$1" method="$2" path="$3" data="${4:-}" token="${5:-$API_TOKEN}"
    local args=(-sS -X "$method" -H "Authorization: Bearer $token")
    if [[ -n "$data" ]]; then args+=(-H 'Content-Type: application/json' -d "$data"); fi
    local result status
    result=$(curl "${args[@]}" -w $'\n%{http_code}' "$BASE_URL$path")
    status="${result##*$'\n'}"
    BODY="${result%$'\n'*}"
    if [[ "$status" != "$expected" ]]; then
        echo "FAIL $method $path: expected $expected, got $status: $BODY" >&2
        exit 1
    fi
    echo "PASS $method $path ($status)"
}
json_id() {
    php -r '$d=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR); if(!isset($d["id"]) || !is_int($d["id"]) || $d["id"]<1) exit(1); echo $d["id"];' <<< "$BODY"
}
assert_json() {
    php -r '
    $d = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    $ok = match ($argv[1]) {
        "has_id" => in_array((int)$argv[2], array_column($d, "id")),
        "tank" => $d["name"] === $argv[2] && (float)$d["gallons"] === (float)$argv[3],
        "reading" => count($d) === 1 && (float)$d[0]["ph"] === 7.0,
        "empty" => $d === [],
        "plant" => count($d) === 1 && $d[0]["name"] === "Java fern",
        default => false
    };
    if (!$ok) { fwrite(STDERR, "FAIL response body check\n"); exit(1); }
    ' "$@" <<< "$BODY"
}
cleanup() {
    if [[ -n "$TANK_ID" ]]; then
        curl -sS -X DELETE -H "Authorization: Bearer $API_TOKEN" "$BASE_URL/tanks/$TANK_ID" >/dev/null || true
    fi
}
trap cleanup EXIT

request 401 POST /tanks '{}' 'wrong-token'
for pair in 'POST /tanks' 'POST /readings' 'DELETE /tanks/1'; do
    read -r method path <<< "$pair"
    status=$(curl -sS -o /dev/null -w '%{http_code}' -X "$method" "$BASE_URL$path")
    [[ "$status" == 401 ]] || { echo "FAIL missing token: $pair"; exit 1; }
    echo "PASS missing token $pair (401)"
done
request 400 POST /tanks '{"name":"","gallons":20}'
request 201 POST /tanks '{"name":"Curl test tank","gallons":20}'
TANK_ID=$(json_id)
request 200 GET /tanks
assert_json has_id "$TANK_ID"
request 200 GET "/tanks/$TANK_ID"
assert_json tank 'Curl test tank' 20
request 200 PUT "/tanks/$TANK_ID" '{"name":"Updated test tank","gallons":25}'
request 200 GET "/tanks/$TANK_ID"
assert_json tank 'Updated test tank' 25
request 401 POST /readings '{}' 'wrong-token'
request 201 POST /readings "{\"tank_id\":$TANK_ID,\"ph\":7,\"temperature_c\":24}"
READING_ID=$(json_id)
request 200 GET "/tanks/$TANK_ID/readings"
assert_json reading
request 200 DELETE "/readings/$READING_ID"
request 200 GET "/tanks/$TANK_ID/readings"
assert_json empty
request 404 DELETE "/readings/$READING_ID"
request 201 POST /plants "{\"tank_id\":$TANK_ID,\"name\":\"Java fern\"}"
request 200 GET "/tanks/$TANK_ID/plants"
assert_json plant
request 401 DELETE "/tanks/$TANK_ID" '' 'wrong-token'
request 400 PUT "/tanks/$TANK_ID" '{bad json'
request 400 GET /tanks/abc
request 404 GET /unknown
request 405 POST "/tanks/$TANK_ID" '{}'
request 200 DELETE "/tanks/$TANK_ID"
request 404 GET "/tanks/$TANK_ID"
TANK_ID=''
echo 'All checks passed.'
