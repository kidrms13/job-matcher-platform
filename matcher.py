import os
import json
import warnings
from http.server import BaseHTTPRequestHandler, HTTPServer

# Suppress background logs and warnings
os.environ["HF_HUB_DISABLE_SYMLINKS_WARNING"] = "1"
os.environ["TRANSFORMERS_VERBOSITY"] = "error"
warnings.filterwarnings("ignore")

import PyPDF2
from sentence_transformers import SentenceTransformer, util

print("Loading Semantic AI brain (all-MiniLM-L6-v2)...")
model = SentenceTransformer('all-MiniLM-L6-v2')
print("AI Brain loaded successfully! Server is ready.")

def extract_text_from_pdf(file_path):
    text = ""
    try:
        if file_path == "NO_RESUME" or not os.path.exists(file_path):
            return ""
        with open(file_path, 'rb') as file:
            reader = PyPDF2.PdfReader(file)
            for page in reader.pages:
                extracted = page.extract_text()
                if extracted:
                    text += extracted + " "
    except Exception:
        return ""
    return text

class AIServerHandler(BaseHTTPRequestHandler):
    def do_POST(self):
        # Read incoming data from PHP
        content_length = int(self.headers['Content-Length'])
        post_data = self.rfile.read(content_length)
        data = json.loads(post_data.decode('utf-8'))
        
        job_reqs = data.get('job_requirements', '')
        applicants = data.get('applicants', [])
        
        # Calculate AI Embeddings
        job_embedding = model.encode(job_reqs, convert_to_tensor=True)
        results = []
        
        for app in applicants:
            resume_text = extract_text_from_pdf(app.get('resume_path', 'NO_RESUME'))
            total_context = resume_text + " " + app.get('db_text', '')
            
            if total_context.strip():
                app_embedding = model.encode(total_context, convert_to_tensor=True)
                cosine_scores = util.cos_sim(job_embedding, app_embedding)
                score = cosine_scores[0][0].item() * 100
            else:
                score = 0.0
                
            results.append({
                "id": str(app.get('id')),
                "score": round(score, 2)
            })
            
        # Send clean JSON response back to PHP
        self.send_response(200)
        self.send_header('Content-Type', 'application/json')
        self.end_headers()
        self.wfile.write(json.dumps(results).encode('utf-8'))

    def log_message(self, format, *args):
        return # Keep console quiet

if __name__ == "__main__":
    # Start the local background service on port 5000
    server = HTTPServer(('127.0.0.1', 5000), AIServerHandler)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nShutting down AI Server.")