<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $survey->name }} - Survey</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .content {
            padding: 30px;
        }
        
        .ticket-info {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 25px;
        }
        
        .ticket-info h3 {
            color: #1976d2;
            margin-bottom: 8px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        
        .form-group .required {
            color: #dc3545;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }
        
        .rating-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .rating-option {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 12px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .rating-option:hover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
        
        .rating-option input[type="radio"] {
            margin: 0;
        }
        
        .rating-option.selected {
            border-color: #007bff;
            background-color: #e3f2fd;
        }
        
        .multiple-choice {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .choice-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .choice-option:hover {
            background-color: #f8f9fa;
        }
        
        .choice-option input[type="radio"] {
            margin: 0;
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40,167,69,0.3);
        }
        
        .submit-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .success-message {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: none;
        }
        
        .error-message {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: none;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #007bff;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 600px) {
            .container {
                margin: 10px;
                border-radius: 0;
            }
            
            .header, .content {
                padding: 20px;
            }
            
            .rating-group {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $survey->name }}</h1>
            @if($survey->description)
                <p>{{ $survey->description }}</p>
            @endif
        </div>
        
        <div class="content">
            @if($ticket)
                <div class="ticket-info">
                    <h3>Related Support Ticket</h3>
                    <p><strong>Subject:</strong> {{ $ticket->subject }}</p>
                    <p><strong>Ticket ID:</strong> #{{ $ticket->id }}</p>
                    <p><strong>Status:</strong> {{ ucfirst($ticket->status) }}</p>
                </div>
            @endif
            
            <div class="success-message" id="successMessage">
                <strong>Thank you!</strong> Your feedback has been submitted successfully.
            </div>
            
            <div class="error-message" id="errorMessage">
                <strong>Error:</strong> <span id="errorText"></span>
            </div>
            
            <form id="surveyForm">
                @csrf
                <input type="hidden" name="tenant_id" value="{{ $tenantId }}">
                @if($ticket)
                    <input type="hidden" name="ticket_id" value="{{ $ticket->id }}">
                @endif
                
                @foreach($survey->questions as $question)
                    <div class="form-group">
                        <label for="question_{{ $question->id }}">
                            {{ $question->question }}
                            @if($question->is_required)
                                <span class="required">*</span>
                            @endif
                        </label>
                        
                        @if($question->type === 'text')
                            <textarea 
                                name="responses[{{ $loop->index }}][answer]" 
                                id="question_{{ $question->id }}"
                                class="form-control" 
                                rows="4"
                                @if($question->is_required) required @endif
                                placeholder="Please share your thoughts..."></textarea>
                            <input type="hidden" name="responses[{{ $loop->index }}][question_id]" value="{{ $question->id }}">
                            
                        @elseif($question->type === 'rating')
                            <div class="rating-group">
                                @for($i = 1; $i <= 5; $i++)
                                    <label class="rating-option" for="rating_{{ $question->id }}_{{ $i }}">
                                        <input 
                                            type="radio" 
                                            name="responses[{{ $loop->index }}][answer]" 
                                            id="rating_{{ $question->id }}_{{ $i }}"
                                            value="{{ $i }}"
                                            @if($question->is_required) required @endif
                                            onchange="selectRating(this)">
                                        <span>{{ $i }}</span>
                                    </label>
                                @endfor
                            </div>
                            <input type="hidden" name="responses[{{ $loop->index }}][question_id]" value="{{ $question->id }}">
                            
                        @elseif($question->type === 'multiple_choice')
                            <div class="multiple-choice">
                                @foreach($question->options as $option)
                                    <label class="choice-option">
                                        <input 
                                            type="radio" 
                                            name="responses[{{ $loop->parent->index }}][answer]" 
                                            value="{{ $option }}"
                                            @if($question->is_required) required @endif>
                                        <span>{{ $option }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <input type="hidden" name="responses[{{ $loop->index }}][question_id]" value="{{ $question->id }}">
                            
                        @elseif($question->type === 'yes_no')
                            <div class="rating-group">
                                <label class="rating-option" for="yes_{{ $question->id }}">
                                    <input 
                                        type="radio" 
                                        name="responses[{{ $loop->index }}][answer]" 
                                        id="yes_{{ $question->id }}"
                                        value="Yes"
                                        @if($question->is_required) required @endif
                                        onchange="selectRating(this)">
                                    <span>Yes</span>
                                </label>
                                <label class="rating-option" for="no_{{ $question->id }}">
                                    <input 
                                        type="radio" 
                                        name="responses[{{ $loop->index }}][answer]" 
                                        id="no_{{ $question->id }}"
                                        value="No"
                                        @if($question->is_required) required @endif
                                        onchange="selectRating(this)">
                                    <span>No</span>
                                </label>
                            </div>
                            <input type="hidden" name="responses[{{ $loop->index }}][question_id]" value="{{ $question->id }}">
                        @endif
                    </div>
                @endforeach
                
                <div class="form-group">
                    <label for="respondent_email">Your Email (Optional)</label>
                    <input 
                        type="email" 
                        name="respondent_email" 
                        id="respondent_email"
                        class="form-control" 
                        placeholder="your.email@example.com">
                </div>
                
                <div class="form-group">
                    <label for="feedback">Additional Comments (Optional)</label>
                    <textarea 
                        name="feedback" 
                        id="feedback"
                        class="form-control" 
                        rows="3"
                        placeholder="Any additional feedback or suggestions..."></textarea>
                </div>
                
                <button type="submit" class="submit-btn" id="submitBtn">
                    Submit Feedback
                </button>
            </form>
            
            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p>Submitting your feedback...</p>
            </div>
        </div>
    </div>

    <script>
        function selectRating(element) {
            // Remove selected class from siblings
            const siblings = element.closest('.rating-group').querySelectorAll('.rating-option');
            siblings.forEach(sibling => sibling.classList.remove('selected'));
            
            // Add selected class to current option
            element.closest('.rating-option').classList.add('selected');
        }
        
        document.getElementById('surveyForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const form = e.target;
            const submitBtn = document.getElementById('submitBtn');
            const loading = document.getElementById('loading');
            const successMessage = document.getElementById('successMessage');
            const errorMessage = document.getElementById('errorMessage');
            
            // Hide previous messages
            successMessage.style.display = 'none';
            errorMessage.style.display = 'none';
            
            // Show loading
            submitBtn.disabled = true;
            loading.style.display = 'block';
            
            try {
                // Prepare form data
                const formData = new FormData(form);
                const responses = [];
                
                // Collect responses
                const responseInputs = form.querySelectorAll('input[name*="[answer]"]');
                responseInputs.forEach((input, index) => {
                    if (input.type === 'radio' && input.checked) {
                        const questionId = form.querySelector(`input[name="responses[${index}][question_id]"]`).value;
                        responses.push({
                            question_id: questionId,
                            answer: input.value
                        });
                    } else if (input.type !== 'radio') {
                        const questionId = form.querySelector(`input[name="responses[${index}][question_id]"]`).value;
                        responses.push({
                            question_id: questionId,
                            answer: input.value
                        });
                    }
                });
                
                // Prepare request data
                const requestData = {
                    responses: responses,
                    ticket_id: formData.get('ticket_id'),
                    respondent_email: formData.get('respondent_email'),
                    feedback: formData.get('feedback')
                };
                
                // Submit the survey
                const response = await fetch(`/survey/{{ $survey->id }}/submit?tenant={{ $tenantId }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || formData.get('_token')
                    },
                    body: JSON.stringify(requestData)
                });
                
                const result = await response.json();
                
                if (response.ok && result.success) {
                    // Show success message
                    successMessage.style.display = 'block';
                    form.style.display = 'none';
                    loading.style.display = 'none';
                } else {
                    // Show error message
                    document.getElementById('errorText').textContent = result.message || 'An error occurred';
                    errorMessage.style.display = 'block';
                    loading.style.display = 'none';
                    submitBtn.disabled = false;
                }
                
            } catch (error) {
                console.error('Error submitting survey:', error);
                document.getElementById('errorText').textContent = 'Network error. Please try again.';
                errorMessage.style.display = 'block';
                loading.style.display = 'none';
                submitBtn.disabled = false;
            }
        });
    </script>
</body>
</html>
