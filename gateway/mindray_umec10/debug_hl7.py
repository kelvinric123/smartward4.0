import hl7

raw_msg = "MSH|^~\\&|MINDRAY|uMEC10|LISTENER|GATEWAY|20231025103000||ORU^R01|10001|P|2.3\rPID|||123456||Doe^John\rOBX|1|NM|8867-4||80|bpm\rOBX|2|NM|2708-6||98|%\r"

with open('debug_output.txt', 'w') as f:
    try:
        f.write(f"Raw message length: {len(raw_msg)}\n")
        h = hl7.parse(raw_msg)
        f.write(f"Message parsed type: {type(h)}\n")
        f.write(f"Number of segments: {len(h)}\n")
        
        for i, segment in enumerate(h):
            f.write(f"Segment {i}: {segment}\n")
            f.write(f"  Type: {type(segment)}\n")
            f.write(f"  ID: {segment[0]}\n")
            
        if 'PID' in h:
            f.write("PID found by key lookup\n")
        else:
            f.write("PID NOT found by key lookup\n")

    except Exception as e:
        f.write(f"Parse error: {e}\n")
