import sys
import PyPDF2
import re
import json

def extract_data_from_pdf(file_path):
    text = ""
    try:
        with open(file_path, 'rb') as file:
            reader = PyPDF2.PdfReader(file)
            for page in reader.pages:
                extracted = page.extract_text()
                if extracted:
                    text += extracted + " "
    except Exception as e:
        return {"error": str(e)}

    # 1. Extract Email using Regex
    email_match = re.search(r'[\w\.-]+@[\w\.-]+', text)
    email = email_match.group(0) if email_match else ""

    # 2. Extract Phone Number using Regex
    phone_match = re.search(r'\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}', text)
    phone = phone_match.group(0) if phone_match else ""

    # 3. Extract Skills (Scanning against a master list of common tech skills)
    master_skills = ["Python", "Java", "C++", "PHP", "SQL", "JavaScript", "HTML", "CSS", "React", "Node.js", "Machine Learning", "Git", "AWS", "Docker"]
    found_skills = []
    
    for skill in master_skills:
        # Check if skill exists in text (case-insensitive)
        if re.search(r'\b' + re.escape(skill) + r'\b', text, re.IGNORECASE):
            found_skills.append(skill)

    # 4. Create a JSON package to send back to PHP
    profile_data = {
        "email_found": email,
        "phone_found": phone,
        "skills_found": ", ".join(found_skills)
    }
    
    return profile_data

if __name__ == "__main__":
    if len(sys.argv) > 1:
        pdf_file_path = sys.argv[1]
        extracted_info = extract_data_from_pdf(pdf_file_path)
        
        # Print the data as a JSON string so PHP can decode it
        print(json.dumps(extracted_info))
    else:
        print(json.dumps({"error": "No file path provided"}))