import requests
import time
import random
import json
from datetime import datetime

BASE_URL = "http://127.0.0.1:8000"
TOTAL_REQUESTS = 3000
ANOMALY_START = None
ANOMALY_END = None

def generate_normal_traffic():
    endpoints = [
        ("/api/normal", 0.70),
        ("/api/slow", 0.15),
        ("/api/slow?hard=1", 0.05),
        ("/api/error", 0.05),
        ("/api/db", 0.03),
        ("/api/validate", 0.02)
    ]
    return random.choices([e[0] for e in endpoints], [e[1] for e in endpoints])[0]

def generate_anomaly_traffic(anomaly_type):
    if anomaly_type == "error_spike":
        return random.choices(["/api/error", "/api/normal", "/api/slow"], [0.40, 0.40, 0.20])[0]
    elif anomaly_type == "latency_spike":
        return random.choices(["/api/slow?hard=1", "/api/normal", "/api/error"], [0.30, 0.50, 0.20])[0]
    return "/api/normal"

def call_endpoint(endpoint):
    url = f"{BASE_URL}{endpoint}"
    try:
        if endpoint == "/api/validate":
            payload = random.choice([
                {"email": "valid@test.com", "age": 30},
                {"email": "invalid", "age": 15},
                {"email": "test@test.com", "age": 25},
                {"email": "", "age": 70}
            ])
            response = requests.post(url, json=payload)
        else:
            response = requests.get(url)
        return {
            "endpoint": endpoint,
            "status_code": response.status_code,
            "timestamp": datetime.now().isoformat()
        }
    except Exception as e:
        return {
            "endpoint": endpoint,
            "status_code": 500,
            "error": str(e),
            "timestamp": datetime.now().isoformat()
        }

def run_traffic():
    global ANOMALY_START, ANOMALY_END
    results = []
    anomaly_type = "error_spike"
    total_duration = 600
    anomaly_start_seconds = random.randint(180, 420)
    
    print(f"Starting traffic generation for {total_duration} seconds...")
    print(f"Anomaly will start at {anomaly_start_seconds} seconds")
    
    start_time = time.time()
    
    for i in range(TOTAL_REQUESTS):
        current_time = time.time()
        elapsed = current_time - start_time
        
        is_anomaly = (anomaly_start_seconds <= elapsed <= anomaly_start_seconds + 120)
        
        if is_anomaly and not ANOMALY_START:
            ANOMALY_START = datetime.now().isoformat()
            print(f"\n*** ANOMALY WINDOW STARTED at {ANOMALY_START} ***\n")
        elif not is_anomaly and ANOMALY_START and not ANOMALY_END:
            ANOMALY_END = datetime.now().isoformat()
            print(f"\n*** ANOMALY WINDOW ENDED at {ANOMALY_END} ***\n")
        
        if is_anomaly:
            endpoint = generate_anomaly_traffic(anomaly_type)
        else:
            endpoint = generate_normal_traffic()
        
        result = call_endpoint(endpoint)
        results.append(result)
        
        time.sleep(0.2)
        
        if (i + 1) % 100 == 0:
            print(f"Completed {i + 1}/{TOTAL_REQUESTS} requests")
    
    ground_truth = {
        "anomaly_start_iso": ANOMALY_START,
        "anomaly_end_iso": ANOMALY_END,
        "anomaly_type": anomaly_type,
        "expected_behavior": f"During anomaly, {anomaly_type} should be clearly visible in Grafana dashboards",
        "total_requests": TOTAL_REQUESTS,
        "timestamp": datetime.now().isoformat()
    }
    
    with open("ground_truth.json", "w") as f:
        json.dump(ground_truth, f, indent=2)
    
    with open("logs.json", "w") as f:
        json.dump(results, f, indent=2)
    
    print("\n=== TRAFFIC GENERATION COMPLETE ===")
    print(f"Ground truth saved to ground_truth.json")
    print(f"Logs saved to logs.json")
    print(f"Total requests: {len(results)}")
    
    status_counts = {}
    for r in results:
        status = r.get("status_code", 500)
        status_counts[status] = status_counts.get(status, 0) + 1
    
    print("\nStatus code distribution:")
    for status, count in sorted(status_counts.items()):
        print(f"  {status}: {count} ({count/len(results)*100:.1f}%)")

if __name__ == "__main__":
    run_traffic()