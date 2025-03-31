# Daily Attendance API Documentation

## Submit Attendance

Submit attendance via API using either username/password or QR code scan.

**Endpoint:** `/wp-json/v1/qr-attendances/submit`  
**Method:** POST

### Authentication Methods

#### 1. Username/Password Authentication

```json
{
    "userName": "john_doe",
    "passWord": "your_password"
}
```

#### 2. QR Code Authentication

```json
{
    "user_id": 123,
    "hash": "generated_hash_from_qr_code"
}
```

### Response Format

```json
{
    "version": "V1",
    "success": true|false,
    "content": "Response message"
}
```

### Example Usage

#### Using Username/Password:
```bash
curl -X POST https://yoursite.com/wp-json/v1/qr-attendances/submit \
-H "Content-Type: application/json" \
-d '{
    "userName": "john_doe",
    "passWord": "your_password"
}'
```

#### Using QR Code Data:
```bash
curl -X POST https://yoursite.com/wp-json/v1/qr-attendances/submit \
-H "Content-Type: application/json" \
-d '{
    "user_id": 123,
    "hash": "generated_hash_from_qr_code"
}'
```

### Success Response Example

```json
{
    "version": "V1",
    "success": true,
    "content": "Attendance marked successfully for John Doe"
}
```

### Error Response Example

```json
{
    "version": "V1",
    "success": false,
    "content": "Invalid authentication data"
}
```

### Notes

1. The QR code contains pre-generated hash that is permanently valid for that user
2. QR codes are safer to use than username/password
3. The hash is generated using user ID and a secret key
4. Each user has their unique QR code that can be used repeatedly

### Implementation Example

```javascript
// QR Code scanning example
function handleQRScan(qrData) {
    fetch('/wp-json/v1/qr-attendances/submit', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(JSON.parse(qrData))
    })
    .then(r => r.json())
    .then(response => {
        if (response.success) {
            console.log('Attendance marked:', response.content);
        } else {
            console.error('Failed:', response.content);
        }
    });
}
```
