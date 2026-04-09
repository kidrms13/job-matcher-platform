import sys
import PyPDF2
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

def extract_text_from_pdf(file_path):
    text = ""
    try:
        with open(file_path, 'rb') as file:
            reader = PyPDF2.PdfReader(file)
            for page in reader.pages:
                if page.extract_text():
                    text += page.extract_text() + " "
        return text
    except Exception as e:
        return ""

def calculate_match(job_description, resume_text):
    if not job_description or not resume_text:
        return 0.0

    documents = [job_description, resume_text]
    vectorizer = TfidfVectorizer(stop_words='english')
    
    tfidf_matrix = vectorizer.fit_transform(documents)
    similarity_score = cosine_similarity(tfidf_matrix[0:1], tfidf_matrix[1:2])[0][0]
    
    return round(similarity_score * 100, 2)

if __name__ == "__main__":
    # We now look for TWO arguments: the file path AND the job requirements
    if len(sys.argv) > 2:
        pdf_file_path = sys.argv[1]
        job_reqs = sys.argv[2] 
        
        resume_text = extract_text_from_pdf(pdf_file_path)
        
        if resume_text.strip():
            score = calculate_match(job_reqs, resume_text)
            print(score)
        else:
            print("0.00")
    else:
        print("Error: Missing arguments")