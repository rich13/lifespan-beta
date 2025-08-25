@props(['personalSpan'])

@php
    // If no personal span, don't show the component
    if (!$personalSpan) {
        return;
    }
    
    // Get user's connections by type (as subject)
    $userConnectionsAsSubject = $personalSpan->connectionsAsSubject()
        ->whereNotNull('connection_span_id')
        ->whereHas('connectionSpan', function($query) {
            $query->whereNotNull('start_year');
        })
        ->where('child_id', '!=', $personalSpan->id)
        ->with(['connectionSpan', 'child', 'type'])
        ->get();

    // Get user's connections by type (as object/child)
    $userConnectionsAsObject = $personalSpan->connectionsAsObject()
        ->whereNotNull('connection_span_id')
        ->whereHas('connectionSpan', function($query) {
            $query->whereNotNull('start_year');
        })
        ->where('parent_id', '!=', $personalSpan->id)
        ->with(['connectionSpan', 'parent', 'type'])
        ->get();

    // Combine and group connections by type
    $allUserConnections = $userConnectionsAsSubject->concat($userConnectionsAsObject);
    $connectionsByType = $allUserConnections->groupBy('type_id');
    
    // Check if this is the first time showing the component (no connections yet)
    $hasAnyConnections = $allUserConnections->count() > 0;
    
    // Check which types need more connections (less than 2)
    $missingTypes = [];
    $targetTypes = ['residence', 'education', 'family'];
    
    foreach ($targetTypes as $type) {
        $count = $connectionsByType->get($type, collect())->count();
        if ($count < 2) {
            $missingTypes[$type] = 2 - $count;
        }
    }
    
    // Only show if there are missing types
    if (empty($missingTypes)) {
        return;
    }
    
    // Define questions for each type in order
    $questions = [
        'residence' => [
            'Where were you born?',
            'Where do you currently live?'
        ],
        'education' => [
            'What primary school did you go to?',
            'What secondary school did you go to?'
        ],
        'family' => [
            'What\'s your mother\'s name?',
            'What\'s your father\'s name?'
        ]
    ];
    
    // Calculate total questions and answered questions for completion modal logic
    $totalQuestions = 0;
    $answeredQuestions = 0;
    foreach ($questions as $type => $typeQuestions) {
        $totalQuestions += count($typeQuestions);
        $currentCount = $connectionsByType->get($type, collect())->count();
        $answeredQuestions += min($currentCount, count($typeQuestions));
    }
    
    // Flatten all questions into a single array with type info
    $allQuestions = [];
    foreach ($questions as $type => $typeQuestions) {
        foreach ($typeQuestions as $index => $question) {
            $allQuestions[] = [
                'type' => $type,
                'question' => $question,
                'index' => $index
            ];
        }
    }
    
    // Find the next question to ask based on existing connections
    $nextQuestion = null;
    $answeredCount = 0;
    $totalQuestions = count($allQuestions);
    
    echo "<!-- Debug: totalQuestions = $totalQuestions -->";
    
    foreach ($allQuestions as $q) {
        $type = $q['type'];
        $questionIndex = $q['index'];
        
        // Get current connection count for this type
        $currentCount = $connectionsByType->get($type, collect())->count();
        
        echo "<!-- Debug: type=$type, questionIndex=$questionIndex, currentCount=$currentCount -->";
        
        // If we haven't asked this question yet (index >= current count)
        if ($questionIndex >= $currentCount) {
            if (!$nextQuestion) {
                $nextQuestion = $q;
                echo "<!-- Debug: nextQuestion set to $type question $questionIndex -->";
            }
        } else {
            $answeredCount++;
            echo "<!-- Debug: answeredCount incremented to $answeredCount -->";
        }
    }
    
    echo "<!-- Debug: final answeredCount = $answeredCount, nextQuestion = " . ($nextQuestion ? 'set' : 'null') . " -->";
    
    // Calculate progress percentage
    $progressPercentage = $totalQuestions > 0 ? round(($answeredCount / $totalQuestions) * 100) : 0;
    
    // If all questions are answered, don't show the component
    if (!$nextQuestion) {
        return;
    }
@endphp

@if($nextQuestion)
    <div class="card mb-3 bg-primary-subtle" id="missing-connections-prompt">
        <script>console.log('Missing connections component rendered with question:', @json($nextQuestion));</script>
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h3 class="h6 mb-0">
                    <i class="bi bi-question-circle text-info me-2"></i>
                    Add some spans...
                </h3>
                <small class="text-muted">{{ $answeredCount }}/{{ $totalQuestions }} questions</small>
            </div>
            <div class="progress" style="height: 6px;">
                <div class="progress-bar bg-info" role="progressbar" 
                     style="width: {{ $progressPercentage }}%" 
                     aria-valuenow="{{ $progressPercentage }}" 
                     aria-valuemin="0" 
                     aria-valuemax="100">
                </div>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                <i class="bi bi-info-circle me-1"></i>
                These will be <strong>placeholders</strong>. You can edit them (and add more) later.
            </p>
            <p class="text-muted small mb-3">
                <i class="bi bi-check-circle-fill me-1"></i>
                Answer them all to get started.
            </p>
            
            @php
                $type = $nextQuestion['type'];
                $question = $nextQuestion['question'];
                $connectionType = \App\Models\ConnectionType::where('type', $type)->first();
                
                // Define explanations for each question type
                $explanations = [
                    'residence' => [
                        'Where were you born?' => 'This will create a "residence" connection starting on your date of birth.',
                        'Where do you currently live?' => 'This will create a "residence" connection starting from when you moved there.'
                    ],
                    'education' => [
                        'What primary school did you go to?' => 'This will create an "education" connection starting when you began primary school.',
                        'What secondary school did you go to?' => 'This will create an "education" connection starting when you began secondary school.'
                    ],
                    'family' => [
                        'What\'s your mother\'s name?' => 'This will create a "family" connection to your mother.',
                        'What\'s your father\'s name?' => 'This will create a "family" connection to your father.'
                    ]
                ];
                
                $explanation = $explanations[$type][$question] ?? 'This will create a connection to help you get started.';
            @endphp
                
                <div class="mb-3">
                    <div class="comparison-input-container">
                        <div class="interactive-card-base">
                            <div class="btn-group btn-group-sm" role="group">
                                <!-- Question -->
                                <button type="button" class="btn inactive">
                                    {{ $question }}
                                </button>
                                
                                <!-- Input field -->
                                <input type="text" 
                                       class="form-control form-control-sm comparison-input-field" 
                                       placeholder="" 
                                       style="width: 200px;"
                                       data-connection-type="{{ $type }}"
                                       data-span-id="{{ $personalSpan->id }}"
                                       data-age="0"
                                       data-question="{{ $question }}"
                                       data-birth-year="{{ $personalSpan->start_year }}"
                                       data-birth-month="{{ $personalSpan->start_month }}"
                                       data-birth-day="{{ $personalSpan->start_day }}">
                                
                                <!-- Submit button -->
                                <button type="button" class="btn btn-primary btn-sm submit-answer" 
                                        data-connection-type="{{ $type }}"
                                        data-span-id="{{ $personalSpan->id }}"
                                        data-age="0"
                                        data-question="{{ $question }}"
                                        data-birth-year="{{ $personalSpan->start_year }}"
                                        data-birth-month="{{ $personalSpan->start_month }}"
                                        data-birth-day="{{ $personalSpan->start_day }}">
                                    <i class="bi bi-plus me-1"></i>Add
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Explanation text -->
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            {{ $explanation }}
                        </small>
                    </div>
                </div>
        </div>
    </div>
@endif



@push('scripts')

<script>
$(document).ready(function() {
    console.log('Missing connections prompt script loaded');
    let searchTimeout = null;
    let activeDropdown = null;
    
    // Debug: Check if elements exist
    const inputFields = $('.comparison-input-field');
    console.log('Found comparison input fields:', inputFields.length);
    inputFields.each(function(index) {
        console.log(`Input ${index}:`, {
            element: this,
            classes: $(this).attr('class'),
            dataConnectionType: $(this).data('connection-type'),
            dataSpanId: $(this).data('span-id')
        });
    });
    
    // Test: Create a simple visible dropdown to test if dropdowns work at all
    setTimeout(() => {
        if (inputFields.length > 0) {
            const testDropdown = $(`
                <div style="position: absolute; top: 100%; left: 0; background: white; border: 2px solid red; padding: 10px; z-index: 9999; display: block;">
                    TEST DROPDOWN - If you see this, dropdowns work!
                </div>
            `);
            inputFields.first().parent().append(testDropdown);
            console.log('Test dropdown added');
            
            // Remove test dropdown after 3 seconds
            setTimeout(() => {
                testDropdown.remove();
            }, 3000);
        }
    }, 1000);
    
    // Debug: Check if any focus events are happening
    $(document).on('focus', 'input', function() {
        console.log('Any input focus event:', {
            element: this,
            classes: $(this).attr('class'),
            isComparisonField: $(this).hasClass('comparison-input-field')
        });
    });
    
    // Handle input interactions for missing connections prompt
    $(document).on('focus', '.comparison-input-field', function() {
        console.log('Focus event triggered on comparison input');
        const input = $(this);
        const connectionType = input.data('connection-type');
        const searchTerm = input.val().trim();
        
        console.log('Focus debug:', {
            input: input[0],
            connectionType: connectionType,
            searchTerm: searchTerm,
            inputClasses: input.attr('class')
        });
        
        // Always show dropdown when focused, even if empty
        if (searchTerm) {
            console.log('Search term exists, calling searchForSpans');
            searchForSpans(searchTerm, connectionType, input);
        } else {
            console.log('No search term, showing empty dropdown');
            showEmptyDropdown(input, connectionType);
        }
    });
    
    $(document).on('input', '.comparison-input-field', function() {
        console.log('Input event triggered on comparison input');
        const input = $(this);
        const connectionType = input.data('connection-type');
        const searchTerm = input.val().trim();
        
        console.log('Input debug:', {
            input: input[0],
            connectionType: connectionType,
            searchTerm: searchTerm,
            searchTermLength: searchTerm.length
        });
        
        // Clear any existing timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        // Search immediately when typing
        if (searchTerm.length > 0) {
            console.log('Search term exists, calling searchForSpans');
            searchForSpans(searchTerm, connectionType, input);
        } else {
            console.log('No search term, showing empty dropdown');
            showEmptyDropdown(input, connectionType);
        }
    });
    
    // Function to estimate primary school start date
    function estimatePrimarySchoolStart(birthYear, birthMonth, birthDay) {
        if (!birthYear || birthYear === '0') return null;
        
        const yearOfBirth = parseInt(birthYear);
        const monthOfBirth = birthMonth && birthMonth !== '0' ? parseInt(birthMonth) : 1;
        
        // If birthday is between Sept 1 and Dec 31, start school the following year
        let startYear;
        if (monthOfBirth >= 9) {
            startYear = yearOfBirth + 5;
        } else {
            startYear = yearOfBirth + 4;
        }
        
        return {
            start_year: startYear,
            start_month: 9,
            start_day: 1
        };
    }
    
    // Function to estimate secondary school start date
    function estimateSecondarySchoolStart(birthYear, birthMonth, birthDay) {
        if (!birthYear || birthYear === '0') return null;
        
        const yearOfBirth = parseInt(birthYear);
        const monthOfBirth = birthMonth && birthMonth !== '0' ? parseInt(birthMonth) : 1;
        
        // If birthday is Sept–Dec, start school the following year
        let startYear;
        if (monthOfBirth >= 9) {
            startYear = yearOfBirth + 12;
        } else {
            startYear = yearOfBirth + 11;
        }
        
        return {
            start_year: startYear,
            start_month: 9,
            start_day: 1
        };
    }
    
    // Handle submit button clicks
    $(document).on('click', '.submit-answer', function() {
        const button = $(this);
        const container = button.closest('.comparison-input-container');
        const input = container.find('.comparison-input-field');
        const connectionType = button.data('connection-type');
        const parentSpanId = button.data('span-id');
        const age = button.data('age');
        const question = button.data('question');
        const searchTerm = input.val().trim();
        
        // Hide dropdown immediately for clean UI
        hideDropdown();
        
        if (!searchTerm) {
            showFeedback('Please enter a value', 'error');
            return;
        }
        
        // Get birth date data
        const birthYear = button.data('birth-year');
        const birthMonth = button.data('birth-month');
        const birthDay = button.data('birth-day');
        
        // Check if this is a question that should use birth date automatically
        if (question === 'Where were you born?' || question === 'What\'s your mother\'s name?' || question === 'What\'s your father\'s name?') {
            // Use the person's birth date automatically
            let dateData = null;
            if (birthYear && birthYear.toString().trim() !== '' && birthYear !== '0') {
                dateData = {
                    start_year: parseInt(birthYear),
                    start_month: birthMonth && birthMonth.toString().trim() !== '' && birthMonth !== '0' ? parseInt(birthMonth) : null,
                    start_day: birthDay && birthDay.toString().trim() !== '' && birthDay !== '0' ? parseInt(birthDay) : null
                };
            }
            
            // Create connection with birth date
            createConnectionWithNewSpan(searchTerm, getAllowedSpanTypes(connectionType)[0], {
                data: function(key) {
                    if (key === 'span-id') return parentSpanId;
                    if (key === 'age') return age;
                    return null;
                }
            }, connectionType, dateData, question);
        } else if (question === 'What primary school did you go to?') {
            // Use estimated primary school start date
            const dateData = estimatePrimarySchoolStart(birthYear, birthMonth, birthDay);
            
            // Create connection with estimated primary school start date
            createConnectionWithNewSpan(searchTerm, getAllowedSpanTypes(connectionType)[0], {
                data: function(key) {
                    if (key === 'span-id') return parentSpanId;
                    if (key === 'age') return age;
                    return null;
                }
            }, connectionType, dateData, question);
        } else if (question === 'What secondary school did you go to?') {
            // Use estimated secondary school start date
            const dateData = estimateSecondarySchoolStart(birthYear, birthMonth, birthDay);
            
            // Create connection with estimated secondary school start date
            createConnectionWithNewSpan(searchTerm, getAllowedSpanTypes(connectionType)[0], {
                data: function(key) {
                    if (key === 'span-id') return parentSpanId;
                    if (key === 'age') return age;
                    return null;
                }
            }, connectionType, dateData, question);
        } else {
            // For other questions, show the date input form
            const span = {
                id: null,
                name: searchTerm,
                type_id: getAllowedSpanTypes(connectionType)[0],
                type_name: getAllowedSpanTypes(connectionType)[0].charAt(0).toUpperCase() + getAllowedSpanTypes(connectionType)[0].slice(1),
                is_placeholder: true
            };
            showConnectionPreview(input, span, connectionType);
        }
    });
    
    // Hide dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.comparison-input-container').length) {
            hideDropdown();
        }
    });
    
    function searchForSpans(searchTerm, connectionType, input) {
        const allowedTypes = getAllowedSpanTypes(connectionType);
        
        if (!allowedTypes || allowedTypes.length === 0) {
            return;
        }
        
        const searchParams = new URLSearchParams({
            q: searchTerm,
            types: allowedTypes.join(',')
        });
        
        const searchUrl = `/spans/api/spans/search?${searchParams.toString()}`;
        
        fetch(searchUrl, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            displaySearchResults(data || [], searchTerm, input, connectionType);
        })
        .catch(error => {
            console.error('Search error:', error);
            displaySearchResults([], searchTerm, input, connectionType);
        });
    }
    
    function displaySearchResults(spans, searchTerm, input, connectionType) {
        hideDropdown();
        
        const dropdown = $(`
            <div class="dropdown-menu show position-absolute" 
                 style="top: 100%; left: 0; right: 0; z-index: 1000; max-height: 200px; overflow-y: auto; width: 100%;">
            </div>
        `);
        
        // Always show existing results if any
        spans.forEach((span, index) => {
            const itemClass = span.is_placeholder ? 'dropdown-item text-muted' : 'dropdown-item';
            const icon = span.is_placeholder ? 'bi-plus-circle' : 'bi-' + getTypeIcon(span.type_id);
            const badge = span.is_placeholder ? '<span class="badge bg-secondary ms-2">New</span>' : '';
            
            const item = $(`
                <a class="${itemClass}" href="#" data-span-id="${span.id || ''}" data-span-name="${span.name}" data-span-type="${span.type_id}">
                    <i class="bi ${icon} me-2"></i>
                    ${span.name}
                    <small class="text-muted">(${span.type_name})</small>
                    ${badge}
                </a>
            `);
            
            item.on('click', function(e) {
                e.preventDefault();
                const question = input.data('question');
                showConnectionPreview(input, span, connectionType, question);
                hideDropdown();
            });
            
            dropdown.append(item);
        });
        
        // Always show the "Add new" option if we have a search term
        if (searchTerm && searchTerm.trim()) {
            const allowedTypes = getAllowedSpanTypes(connectionType);
            const spanType = allowedTypes[0]; // Use the first allowed type
            const spanTypeName = spanType.charAt(0).toUpperCase() + spanType.slice(1); // Capitalize first letter
            
            const addNewItem = $(`
                <a class="dropdown-item text-primary" href="#" data-span-id="" data-span-name="${searchTerm}" data-span-type="${spanType}">
                    <i class="bi bi-plus-circle me-2"></i>
                    Add "${searchTerm}" as new ${spanTypeName}
                    <span class="badge bg-primary ms-2">New</span>
                </a>
            `);
            
            addNewItem.on('click', function(e) {
                e.preventDefault();
                const span = {
                    id: null,
                    name: searchTerm,
                    type_id: spanType,
                    type_name: spanTypeName,
                    is_placeholder: true
                };
                const question = input.data('question');
                showConnectionPreview(input, span, connectionType, question);
                hideDropdown();
            });
            
            dropdown.append(addNewItem);
        }
        
        // Append to the container instead of the button group to avoid clipping
        input.closest('.comparison-input-container').append(dropdown);
        activeDropdown = dropdown;
    }
    
    function createConnectionWithExistingSpan(spanId, spanName, spanType, input, connectionType, dateData = null, question = null) {
        // Prevent duplicate submissions
        if (window.isCreatingConnection) {
            console.log('Connection creation already in progress, ignoring duplicate request');
            return;
        }
        
        window.isCreatingConnection = true;
        
        const parentSpanId = input.data('span-id');
        const age = input.data('age');
        
        // Check if this is a parent question that needs reversed relationship
        const isParentQuestion = question === "What's your mother's name?" || question === "What's your father's name?";
        
        // Prepare connection data
        const connectionData = {
            parent_id: isParentQuestion ? spanId : parentSpanId,
            child_id: isParentQuestion ? parentSpanId : spanId,
            type_id: connectionType,
            age: age
        };
        
        // Add date fields if provided
        if (dateData) {
            if (dateData.start_year) connectionData.start_year = parseInt(dateData.start_year);
            if (dateData.start_month) connectionData.start_month = parseInt(dateData.start_month);
            if (dateData.start_day) connectionData.start_day = parseInt(dateData.start_day);
        }
        
        console.log('Creating connection:', connectionData);
        
        fetch('/spans/api/connections', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(connectionData)
        })
        .then(response => response.json())
        .then(data => {
            window.isCreatingConnection = false;
            if (data.success) {
                showFeedback('Connection created successfully!', 'success');
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showFeedback('Failed to create connection: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            window.isCreatingConnection = false;
            console.error('Error creating connection:', error);
            showFeedback('Error creating connection', 'error');
        });
    }
    
    function createConnectionWithNewSpan(spanName, spanType, input, connectionType, dateData = null, question = null) {
        const parentSpanId = input.data('span-id');
        const age = input.data('age');
        
        // Get the correct span type for this connection type
        const allowedTypes = getAllowedSpanTypes(connectionType);
        const correctSpanType = allowedTypes[0]; // Use the first allowed type
        
        console.log('Creating new span:', {
            name: spanName,
            type_id: correctSpanType,
            connectionType: connectionType,
            allowedTypes: allowedTypes,
            dateData: dateData
        });
        
        // Prepare span data (without dates - dates go on the connection, not the span)
        const spanData = {
            name: spanName,
            type_id: correctSpanType,
            access_level: 'private',
            state: 'placeholder'  // Set as placeholder so user can edit later
        };
        
        fetch('/spans/api/spans', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(spanData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                return createConnectionWithExistingSpan(data.span.id, spanName, correctSpanType, input, connectionType, dateData, question);
            } else {
                showFeedback('Failed to create span: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error creating span:', error);
            showFeedback('Error creating span', 'error');
        });
    }
    
    function showConnectionPreview(input, span, connectionType, question) {
        const container = input.closest('.comparison-input-container');
        const age = input.data('age');
        const parentSpanId = input.data('span-id');
        
        // Check if this is a question that should use birth date automatically
        if (question === 'Where were you born?' || question === 'What\'s your mother\'s name?' || question === 'What\'s your father\'s name?') {
            // Use birth date automatically and skip the date input form
            const birthYear = input.data('birth-year');
            const birthMonth = input.data('birth-month');
            const birthDay = input.data('birth-day');
            
            // Only create dateData if there's actually a birth year
            let dateData = null;
            if (birthYear && birthYear.toString().trim() !== '' && birthYear !== '0') {
                dateData = {
                    start_year: parseInt(birthYear),
                    start_month: birthMonth && birthMonth.toString().trim() !== '' && birthMonth !== '0' ? parseInt(birthMonth) : null,
                    start_day: birthDay && birthDay.toString().trim() !== '' && birthDay !== '0' ? parseInt(birthDay) : null
                };
            }
            
            // Create connection immediately with birth date
            const mockInput = {
                data: function(key) {
                    if (key === 'span-id') return parentSpanId;
                    if (key === 'age') return age;
                    return null;
                }
            };
            
            if (span.id) {
                createConnectionWithExistingSpan(span.id, span.name, span.type_id, mockInput, connectionType, dateData, question);
            } else {
                createConnectionWithNewSpan(span.name, span.type_id, mockInput, connectionType, dateData, question);
            }
            return;
        }
        
        // Get estimated dates for education questions
        let estimatedDate = null;
        if (question === 'What primary school did you go to?') {
            estimatedDate = estimatePrimarySchoolStart(input.data('birth-year'), input.data('birth-month'), input.data('birth-day'));
        } else if (question === 'What secondary school did you go to?') {
            estimatedDate = estimateSecondarySchoolStart(input.data('birth-year'), input.data('birth-month'), input.data('birth-day'));
        }
        
        // For other questions, show the date input form
        const previewHtml = `
            <div class="interactive-card-base">
                <div class="mb-3">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-info">
                            ${span.name}
                        </button>
                        ${span.is_placeholder ? '<button type="button" class="btn btn-outline-warning"><small>New</small></button>' : ''}
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label small">When did this ${connectionType} start? <span class="text-danger">*</span></label>
                    <div class="row g-2">
                        <div class="col-4">
                            <input type="number" class="form-control form-control-sm" id="start-year" placeholder="Year" min="1000" max="2100" required value="${estimatedDate ? estimatedDate.start_year : ''}">
                        </div>
                        <div class="col-4">
                            <select class="form-select form-select-sm" id="start-month">
                                <option value="">Month (optional)</option>
                                <option value="1" ${estimatedDate && estimatedDate.start_month === 1 ? 'selected' : ''}>January</option>
                                <option value="2" ${estimatedDate && estimatedDate.start_month === 2 ? 'selected' : ''}>February</option>
                                <option value="3" ${estimatedDate && estimatedDate.start_month === 3 ? 'selected' : ''}>March</option>
                                <option value="4" ${estimatedDate && estimatedDate.start_month === 4 ? 'selected' : ''}>April</option>
                                <option value="5" ${estimatedDate && estimatedDate.start_month === 5 ? 'selected' : ''}>May</option>
                                <option value="6" ${estimatedDate && estimatedDate.start_month === 6 ? 'selected' : ''}>June</option>
                                <option value="7" ${estimatedDate && estimatedDate.start_month === 7 ? 'selected' : ''}>July</option>
                                <option value="8" ${estimatedDate && estimatedDate.start_month === 8 ? 'selected' : ''}>August</option>
                                <option value="9" ${estimatedDate && estimatedDate.start_month === 9 ? 'selected' : ''}>September</option>
                                <option value="10" ${estimatedDate && estimatedDate.start_month === 10 ? 'selected' : ''}>October</option>
                                <option value="11" ${estimatedDate && estimatedDate.start_month === 11 ? 'selected' : ''}>November</option>
                                <option value="12" ${estimatedDate && estimatedDate.start_month === 12 ? 'selected' : ''}>December</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <input type="number" class="form-control form-control-sm" id="start-day" placeholder="Day (optional)" min="1" max="31" value="${estimatedDate ? estimatedDate.start_day : ''}">
                        </div>
                    </div>
                    <div class="form-text small">
                        ${estimatedDate ? 'Estimated start date based on your birth date. You can adjust if needed.' : 'Just the year is fine, or year and month if you know it'}
                    </div>
                </div>
                
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-success" id="save-connection-btn" 
                            data-span-id="${span.id || ''}" 
                            data-span-name="${span.name}" 
                            data-span-type="${span.type_id}" 
                            data-connection-type="${connectionType}" 
                            data-parent-span-id="${parentSpanId}" 
                            data-age="${age}" 
                            data-question="${question}" 
                            data-birth-year="${input.data('birth-year')}" 
                            data-birth-month="${input.data('birth-month')}" 
                            data-birth-day="${input.data('birth-day')}" 
                            disabled>
                        <i class="bi bi-check me-1"></i>Save
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="cancelConnection()">
                        <i class="bi bi-x me-1"></i>Cancel
                    </button>
                </div>
            </div>
        `;
        
        container.html(previewHtml);
        
        // Store birth date data globally for validation
        window.currentBirthYear = input.data('birth-year');
        window.currentBirthMonth = input.data('birth-month');
        window.currentBirthDay = input.data('birth-day');
        
        // Add event listeners for date validation
        const yearInput = document.getElementById('start-year');
        const saveBtn = document.getElementById('save-connection-btn');
        
        function validateDateInputs() {
            const year = yearInput.value.trim();
            const isValid = year !== '' && !isNaN(parseInt(year)) && parseInt(year) >= 1000 && parseInt(year) <= 2100;
            saveBtn.disabled = !isValid;
        }
        
        yearInput.addEventListener('input', validateDateInputs);
        yearInput.addEventListener('change', validateDateInputs);
        
        // Initial validation
        validateDateInputs();
        
        // Add click handler for save button
        saveBtn.addEventListener('click', function() {
            const spanId = this.getAttribute('data-span-id');
            const spanName = this.getAttribute('data-span-name');
            const spanType = this.getAttribute('data-span-type');
            const connectionType = this.getAttribute('data-connection-type');
            const parentSpanId = this.getAttribute('data-parent-span-id');
            const age = this.getAttribute('data-age');
            const question = this.getAttribute('data-question');
            
            saveConnectionWithDate(spanId, spanName, spanType, connectionType, parentSpanId, age, question);
        });
    }
    
    function saveConnectionWithDate(spanId, spanName, spanType, connectionType, parentSpanId, age, question) {
        const ageNum = parseInt(age);
        
        // Get date values from the form
        const startYear = document.getElementById('start-year').value;
        const startMonth = document.getElementById('start-month').value;
        const startDay = document.getElementById('start-day').value;
        
        // Validate that we have at least a year
        if (!startYear || startYear.trim() === '' || isNaN(parseInt(startYear))) {
            showFeedback('Please enter at least a year', 'error');
            return;
        }
        
        // Get birth date from global variables
        const birthYear = window.currentBirthYear;
        const birthMonth = window.currentBirthMonth;
        const birthDay = window.currentBirthDay;
        
        console.log('Birth date data:', { birthYear, birthMonth, birthDay });
        console.log('Input date:', { startYear, startMonth, startDay });
        
        // Validate that the date isn't before birth
        if (birthYear && birthYear !== '' && birthYear !== '0') {
            const inputYear = parseInt(startYear);
            const birthYearNum = parseInt(birthYear);
            
            if (inputYear < birthYearNum) {
                showFeedback('This date disobeys the laws of time! You can\'t have done something before you were born.', 'error');
                return;
            }
            
            // If same year, check month and day
            if (inputYear === birthYearNum && birthMonth && birthMonth !== '' && birthMonth !== '0') {
                const inputMonth = startMonth && startMonth.trim() !== '' ? parseInt(startMonth) : 12; // Default to December if no month
                const birthMonthNum = parseInt(birthMonth);
                
                if (inputMonth < birthMonthNum) {
                    showFeedback('This date disobeys the laws of time! You can\'t have done something before you were born.', 'error');
                    return;
                }
                
                // If same month, check day
                if (inputMonth === birthMonthNum && birthDay && birthDay !== '' && birthDay !== '0') {
                    const inputDay = startDay && startDay.trim() !== '' ? parseInt(startDay) : 31; // Default to end of month if no day
                    const birthDayNum = parseInt(birthDay);
                    
                    if (inputDay < birthDayNum) {
                        showFeedback('This date disobeys the laws of time! You can\'t have done something before you were born.', 'error');
                        return;
                    }
                }
            }
        }
        
        // Create dateData with required year and optional month/day
        const dateData = {
            start_year: parseInt(startYear),
            start_month: startMonth && startMonth.trim() !== '' ? parseInt(startMonth) : null,
            start_day: startDay && startDay.trim() !== '' ? parseInt(startDay) : null
        };
        
        const mockInput = {
            data: function(key) {
                if (key === 'span-id') return parentSpanId;
                if (key === 'age') return ageNum;
                return null;
            }
        };
        
        if (spanId) {
            createConnectionWithExistingSpan(spanId, spanName, spanType, mockInput, connectionType, dateData, question);
        } else {
            createConnectionWithNewSpan(spanName, spanType, mockInput, connectionType, dateData, question);
        }
    }
    
    function saveConnection(spanId, spanName, spanType, connectionType, parentSpanId, age) {
        const ageNum = parseInt(age);
        
        const mockInput = {
            data: function(key) {
                if (key === 'span-id') return parentSpanId;
                if (key === 'age') return ageNum;
                return null;
            }
        };
        
        if (spanId) {
            createConnectionWithExistingSpan(spanId, spanName, spanType, mockInput, connectionType);
        } else {
            createConnectionWithNewSpan(spanName, spanType, mockInput, connectionType);
        }
    }
    
    function cancelConnection() {
        showFeedback('Connection creation cancelled', 'info');
        setTimeout(() => {
            location.reload();
        }, 1000);
    }
    
    function getAllowedSpanTypes(connectionType) {
        const typeMap = {
            'residence': ['place'],
            'employment': ['organisation'],
            'education': ['organisation'],
            'membership': ['organisation', 'band'],
            'has_role': ['organisation'],
            'at_organisation': ['organisation'],
            'travel': ['place'],
            'participation': ['event'],
            'ownership': ['thing'],
            'contains': ['thing'],
            'created': ['thing'],
            'family': ['person'],
            'relationship': ['person'],
            'friend': ['person'],
            'located': ['place']
        };
        
        return typeMap[connectionType] || ['person'];
    }
    
    function getTypeIcon(typeId) {
        const iconMap = {
            'person': 'person',
            'place': 'geo-alt',
            'organisation': 'building',
            'event': 'calendar-event',
            'thing': 'box',
            'band': 'music-note',
            'role': 'person-badge'
        };
        
        return iconMap[typeId] || 'question-circle';
    }
    
    function hideDropdown() {
        console.log('hideDropdown called, activeDropdown:', activeDropdown);
        if (activeDropdown) {
            activeDropdown.remove();
            activeDropdown = null;
        }
    }
    
    function showFeedback(message, type) {
        const alertClass = type === 'success' ? 'alert-success' : 
                          type === 'error' ? 'alert-danger' : 
                          type === 'info' ? 'alert-info' : 'alert-secondary';
        const iconClass = type === 'success' ? 'check-circle' : 
                         type === 'error' ? 'exclamation-triangle' : 
                         type === 'info' ? 'info-circle' : 'question-circle';
        
        const feedback = $(`
            <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
                 style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                <i class="bi bi-${iconClass} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
        
        $('body').append(feedback);
        
        setTimeout(() => {
            feedback.alert('close');
        }, 3000);
    }
    
    function showEmptyDropdown(input, connectionType) {
        console.log('showEmptyDropdown called with:', { connectionType });
        
        hideDropdown();
        
        const allowedTypes = getAllowedSpanTypes(connectionType);
        const typeNames = allowedTypes.map(type => type.charAt(0).toUpperCase() + type.slice(1)).join(', ');
        
        console.log('Allowed types:', allowedTypes, 'Type names:', typeNames);
        
        const dropdown = $(`
            <div class="dropdown-menu show position-absolute" 
                 style="top: 100%; left: 0; right: 0; z-index: 1000; width: 100%; display: block !important; visibility: visible !important; opacity: 1 !important; background: white; border: 1px solid #dee2e6; border-radius: 0.375rem; box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);">
                <div class="dropdown-item text-muted">
                    <i class="bi bi-search me-2"></i>
                    Start typing to search for ${typeNames}...
                </div>
                <div class="dropdown-item text-muted">
                    <i class="bi bi-plus-circle me-2"></i>
                    Or type a new name and click "Add"
                </div>
            </div>
        `);
        
        console.log('Appending empty dropdown to input parent');
        console.log('Dropdown element:', dropdown[0]);
        console.log('Input parent:', input.parent()[0]);
        console.log('Input container:', input.closest('.comparison-input-container')[0]);
        
        // Append to the container instead of the button group to avoid clipping
        input.closest('.comparison-input-container').append(dropdown);
        activeDropdown = dropdown;
        
        // Debug: Check if dropdown is visible
        setTimeout(() => {
            const dropdownElement = input.parent().find('.dropdown-menu').last();
            console.log('Dropdown visibility check:', {
                element: dropdownElement[0],
                isVisible: dropdownElement.is(':visible'),
                display: dropdownElement.css('display'),
                opacity: dropdownElement.css('opacity'),
                zIndex: dropdownElement.css('z-index'),
                position: dropdownElement.css('position'),
                top: dropdownElement.css('top'),
                left: dropdownElement.css('left')
            });
        }, 100);
    }
    
    // Make functions globally accessible
    window.saveConnection = saveConnection;
    window.saveConnectionWithDate = saveConnectionWithDate;
    window.cancelConnection = cancelConnection;
});
</script>


@endpush 