# Daily Attendance API Documentation

## Submit Attendance

Submit attendance for a user via API.

**Endpoint:** `/wp-json/v1/attendances/submit`

**Method:** POST

### Parameters

| Parameter | Type   | Required | Description        |
|-----------|--------|----------|--------------------|
| userName  | string | Yes      | WordPress username |
| passWord  | string | Yes      | WordPress password |

### Response Format

```json
{
    "version": "V1",
    "success": true|false,
    "content": "Response message"
}
```

### Success Response Example

```json
{
    "version": "V1",
    "success": true,
    "content": "Hello JohnDoe, Attendance added successfully"
}
```

### Error Response Example

```json
{
    "version": "V1", 
    "success": false,
    "content": "Invalid username or password!"
}
```

### Sample Usage

```curl
curl -X POST https://mysite.com/wp-json/v1/attendances/submit \
  -H "Content-Type: application/json" \
  -d '{"userName":"john","passWord":"password123"}'
```
