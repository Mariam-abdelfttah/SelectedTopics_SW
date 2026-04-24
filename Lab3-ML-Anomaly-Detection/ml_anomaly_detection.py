import requests
import pandas as pd
import numpy as np
from sklearn.ensemble import IsolationForest
import matplotlib.pyplot as plt
from datetime import datetime, timedelta

print("=" * 60)
print("🧠 AIOps ML Anomaly Detection (Prometheus-based)")
print("=" * 60)

# Configuration
PROMETHEUS_URL = "http://localhost:9090/api/v1/query_range"
STEP = "30s"
HOURS_BACK = 2

def fetch_metric(metric_name, start, end):
    params = {"query": metric_name, "start": start, "end": end, "step": STEP}
    try:
        response = requests.get(PROMETHEUS_URL, params=params, timeout=30)
        if response.status_code == 200:
            data = response.json()
            return data['data']['result'] if 'data' in data else []
    except Exception as e:
        print(f"   ❌ Error fetching {metric_name}: {e}")
        return []

def extract_time_series(results, metric_name):
    records = []
    for result in results:
        if 'values' in result:
            for ts, val in result['values']:
                t = datetime.fromtimestamp(float(ts))
                # Round to nearest second to avoid duplicates
                t = t.replace(microsecond=0)
                records.append({'timestamp': t, metric_name: float(val)})
    
    if not records:
        return pd.DataFrame()
    
    df = pd.DataFrame(records)
    # Remove duplicate timestamps (keep first)
    df = df.drop_duplicates(subset=['timestamp'])
    df.set_index('timestamp', inplace=True)
    # Resample to regular 30s intervals
    df = df.resample('30S').mean()
    return df

# Check Prometheus
print("\n📡 Checking Prometheus connection...")
try:
    test_req = requests.get("http://localhost:9090/-/healthy", timeout=5)
    if test_req.status_code != 200:
        print("❌ Prometheus not healthy")
        exit(1)
    print("✅ Prometheus is running")
except:
    print("❌ Cannot connect to Prometheus")
    exit(1)

# Time range
end_time = datetime.now()
start_time = end_time - timedelta(hours=HOURS_BACK)
start_ts = int(start_time.timestamp())
end_ts = int(end_time.timestamp())

print(f"\n📊 Fetching data from {start_time.strftime('%H:%M:%S')} to {end_time.strftime('%H:%M:%S')}")

# Fetch metrics
print("\n📈 Fetching metrics:")
print("   • http_requests_total...")
requests_data = fetch_metric("http_requests_total", start_ts, end_ts)

print("   • http_errors_total...")
errors_data = fetch_metric("http_errors_total", start_ts, end_ts)

print("   • http_request_duration_seconds_sum...")
latency_data = fetch_metric("http_request_duration_seconds_sum", start_ts, end_ts)

# Build DataFrames
df_requests = extract_time_series(requests_data, 'request_rate')
df_errors = extract_time_series(errors_data, 'error_count')
df_latency = extract_time_series(latency_data, 'avg_latency')

print(f"\n📊 Data points received:")
print(f"   • Requests: {len(df_requests)}")
print(f"   • Errors: {len(df_errors)}")
print(f"   • Latency: {len(df_latency)}")

# Combine all timestamps
all_timestamps = pd.Series(index=sorted(set(df_requests.index) | set(df_errors.index) | set(df_latency.index)))

df = pd.DataFrame(index=all_timestamps.index)

if not df_requests.empty:
    df['request_rate'] = df_requests['request_rate']
if not df_errors.empty:
    df['error_count'] = df_errors['error_count']
if not df_latency.empty:
    df['avg_latency'] = df_latency['avg_latency']

# Fill missing values
df = df.fillna(0)

# Create ML features
df['error_rate'] = df['error_count'] / (df['request_rate'] + 0.001)
df['max_latency'] = df['avg_latency'].rolling(3, min_periods=1).max()
df['latency_std'] = df['avg_latency'].rolling(3, min_periods=1).std().fillna(0)

# Save dataset
df.to_csv('aiops_dataset.csv')
print(f"\n✅ Dataset saved: {len(df)} samples")

if len(df) < 10:
    print("❌ Not enough data. Run traffic generator for a few minutes then try again.")
    exit(1)

# Train Isolation Forest
features = ['avg_latency', 'max_latency', 'request_rate', 'error_rate', 'latency_std']
X = df[features].fillna(0)

# Use first 60% as normal period
split = max(2, int(0.6 * len(X)))
X_train = X[:split]

print(f"\n🤖 Training Isolation Forest on {len(X_train)} normal samples...")

model = IsolationForest(contamination=0.1, random_state=42)
model.fit(X_train)

# Predict
df['anomaly_score'] = -model.score_samples(X)
df['is_anomaly'] = model.predict(X)
df['is_anomaly'] = df['is_anomaly'].map({1: 0, -1: 1})

# Save predictions
df[['anomaly_score', 'is_anomaly']].to_csv('anomaly_predictions.csv')
print(f"✅ Predictions saved. Anomalies detected: {df['is_anomaly'].sum()}")

# Visualization
fig, axes = plt.subplots(2, 1, figsize=(14, 8))

# Latency plot
ax1 = axes[0]
ax1.plot(df.index, df['avg_latency'], 'b-', label='Avg Latency', alpha=0.7)
anomalies = df[df['is_anomaly'] == 1]
if len(anomalies) > 0:
    ax1.scatter(anomalies.index, anomalies['avg_latency'], color='red', s=50, label='Anomaly', zorder=5)
ax1.set_ylabel('Latency (ms)')
ax1.set_title('Latency Timeline with ML Anomaly Detection')
ax1.legend()
ax1.grid(True, alpha=0.3)

# Error rate plot
ax2 = axes[1]
ax2.plot(df.index, df['error_rate'], 'g-', label='Error Rate', alpha=0.7)
if len(anomalies) > 0:
    ax2.scatter(anomalies.index, anomalies['error_rate'], color='red', s=50, label='Anomaly', zorder=5)
ax2.set_ylabel('Error Rate')
ax2.set_xlabel('Time')
ax2.set_title('Error Rate Timeline with ML Anomaly Detection')
ax2.legend()
ax2.grid(True, alpha=0.3)

plt.tight_layout()
plt.savefig('aiops_ml_anomalies.png', dpi=150)
print("✅ Plot saved: aiops_ml_anomalies.png")

print("\n" + "=" * 60)
print(f"🎉 Lab 3 Completed Successfully!")
print(f"   • Dataset: {len(df)} samples")
print(f"   • Anomalies: {df['is_anomaly'].sum()}")
print("=" * 60)