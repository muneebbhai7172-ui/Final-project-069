<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Medicine Suggestions API</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        .test-section {
            background: #f5f5f5;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        input {
            padding: 10px;
            width: 300px;
            font-size: 16px;
        }
        button {
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
        }
        pre {
            background: #fff;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow-x: auto;
        }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Medicine Suggestions API Test</h1>
    
    <div class="test-section">
        <h2>Test Medicine Suggestions</h2>
        <input type="text" id="searchInput" placeholder="Type medicine name (e.g., para, asp, vita)" value="para">
        <button onclick="testSuggestions()">Search</button>
        
        <h3>API Response:</h3>
        <div id="result"></div>
    </div>

    <div class="test-section">
        <h2>Direct Database Test</h2>
        <button onclick="testDatabase()">Test Database Connection</button>
        <div id="dbResult"></div>
    </div>

    <script>
        function testSuggestions() {
            const searchTerm = document.getElementById('searchInput').value;
            const resultDiv = document.getElementById('result');
            
            resultDiv.innerHTML = '<p>Loading...</p>';
            
            fetch(`api/medicine_suggestions.php?q=${encodeURIComponent(searchTerm)}&type=both&limit=10`)
                .then(response => response.json())
                .then(data => {
                    resultDiv.innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                    
                    if (data.success && data.suggestions && data.suggestions.length > 0) {
                        resultDiv.innerHTML += '<p class="success">✓ API is working! Found ' + data.suggestions.length + ' suggestions.</p>';
                    } else {
                        resultDiv.innerHTML += '<p class="error">✗ No suggestions found or API returned error.</p>';
                    }
                })
                .catch(error => {
                    resultDiv.innerHTML = '<p class="error">Error: ' + error.message + '</p>';
                });
        }
        
        function testDatabase() {
            const resultDiv = document.getElementById('dbResult');
            resultDiv.innerHTML = '<p>Testing database connection...</p>';
            
            fetch('test_db_connection.php')
                .then(response => response.json())
                .then(data => {
                    resultDiv.innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                    
                    if (data.success) {
                        resultDiv.innerHTML += '<p class="success">✓ Database connection successful! Found ' + data.medicine_count + ' medicines.</p>';
                    } else {
                        resultDiv.innerHTML += '<p class="error">✗ Database connection failed: ' + data.message + '</p>';
                    }
                })
                .catch(error => {
                    resultDiv.innerHTML = '<p class="error">Error: ' + error.message + '</p>';
                });
        }
        
        // Auto-test on page load
        window.onload = function() {
            testSuggestions();
        };
    </script>
</body>
</html>
