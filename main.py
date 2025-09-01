import os
import subprocess
import signal
import time
from flask import Flask, request, Response
import requests
import atexit

# Create Flask app to work with gunicorn
app = Flask(__name__)
app.secret_key = os.environ.get("SESSION_SECRET", "velvet-vogue-secret-key")

# Global variable to track PHP server
php_process = None
PHP_PORT = 9001

def cleanup_php_processes():
    """Kill any existing PHP development servers"""
    try:
        subprocess.run(["pkill", "-f", "php -S"], check=False)
        time.sleep(1)
    except:
        pass

def start_php_server():
    """Start PHP built-in development server"""
    global php_process
    try:
        # Clean up any existing PHP servers first
        cleanup_php_processes()
        
        print(f"Starting PHP development server on port {PHP_PORT}...")
        php_process = subprocess.Popen(
            ["php", "-S", f"127.0.0.1:{PHP_PORT}", "-t", "."],
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            preexec_fn=os.setsid  # Create new process group
        )
        
        # Wait a moment for server to start
        time.sleep(3)
        
        # Check if process is still running
        if php_process.poll() is None:
            print(f"PHP server is running on port {PHP_PORT}")
            return True
        else:
            print("PHP server failed to start")
            stdout, stderr = php_process.communicate()
            if stderr:
                print(f"PHP server error: {stderr.decode()}")
            return False
            
    except Exception as e:
        print(f"Error starting PHP server: {e}")
        return False

def wait_for_php_server():
    """Wait for PHP server to be ready"""
    for i in range(20):  # Wait up to 20 seconds
        try:
            response = requests.get(f"http://127.0.0.1:{PHP_PORT}", timeout=2)
            if response.status_code in [200, 302, 404]:  # Any valid HTTP response
                print(f"PHP server responded with status {response.status_code}")
                return True
        except requests.exceptions.RequestException:
            pass
        except Exception as e:
            print(f"Unexpected error checking PHP server: {e}")
        time.sleep(1)
    print("PHP server failed to respond after 20 seconds")
    return False

def cleanup_on_exit():
    """Clean up PHP processes on exit"""
    global php_process
    if php_process:
        try:
            os.killpg(os.getpgid(php_process.pid), signal.SIGTERM)
        except:
            pass

atexit.register(cleanup_on_exit)

# Initialize PHP server immediately
print("Initializing PHP server...")
if start_php_server() and wait_for_php_server():
    print("PHP server is ready to serve requests")
else:
    print("Warning: PHP server failed to initialize properly")

@app.route('/', defaults={'path': ''})
@app.route('/<path:path>')
def proxy_to_php(path):
    """Proxy all requests to the PHP server"""
    try:
        # Build URL for PHP server
        url = f"http://127.0.0.1:{PHP_PORT}/{path}"
        if request.query_string:
            url += f"?{request.query_string.decode()}"
        
        # Prepare headers (exclude problematic ones for proxying)
        headers = dict(request.headers)
        headers.pop('Host', None)
        headers.pop('Content-Length', None)
        
        # Forward the request to PHP server
        if request.method == 'GET':
            response = requests.get(url, headers=headers, timeout=30)
        elif request.method == 'POST':
            response = requests.post(
                url, 
                data=request.get_data(),
                headers=headers,
                timeout=30
            )
        else:
            response = requests.request(
                method=request.method,
                url=url,
                headers=headers,
                data=request.get_data(),
                timeout=30
            )
        
        # Create Flask response from PHP response
        response_headers = dict(response.headers)
        # Remove problematic headers
        response_headers.pop('transfer-encoding', None)
        response_headers.pop('connection', None)
        
        flask_response = Response(
            response.content,
            status=response.status_code,
            headers=response_headers
        )
        
        return flask_response
        
    except requests.exceptions.RequestException as e:
        print(f"Error proxying request to {url}: {e}")
        return f"<h1>Service Unavailable</h1><p>The PHP server is not responding. Error: {e}</p>", 503

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)