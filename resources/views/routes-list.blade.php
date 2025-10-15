<!DOCTYPE html>
<html>
<head>
    <title>Available Routes</title>
    <style>
        body { font-family: sans-serif; background: #f9f9f9; padding: 20px; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 8px 12px; border: 1px solid #ccc; }
        th { background: #eee; }
    </style>
</head>
<body>
<h1>Available Routes</h1>
<table>
    <thead>
    <tr>
        <th>Method</th>
        <th>URI</th>
        <th>Name</th>
        <th>Action</th>
    </tr>
    </thead>
    <tbody>
    @foreach($routes as $route)
        <tr>
            <td>{{ $route['method'] }}</td>
            <td>{{ $route['uri'] }}</td>
            <td>{{ $route['name'] ?? '-' }}</td>
            <td>{{ $route['action'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
