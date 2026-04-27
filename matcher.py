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
    # We now expect at least TWO arguments, but potentially THREE (the DB profile text)
    if len(sys.argv) > 2:
        pdf_file_path = sys.argv[1]
        job_reqs = sys.argv[2] 
        
        # Capture the database text if PHP sent it, otherwise default to empty string
        db_profile_text = sys.argv[3] if len(sys.argv) > 3 else ""
        
        # 1. Read the PDF
        resume_text = extract_text_from_pdf(pdf_file_path)
        
        # 2. Combine the PDF text AND the Database text into one massive Context Block
        total_applicant_context = resume_text + " " + db_profile_text
        
        # 3. Calculate the match based on the combined data
        if total_applicant_context.strip():
            score = calculate_match(job_reqs, total_applicant_context)
            print(score)
        else:
            print("0.00")
    else:
        print("Error: Missing arguments")