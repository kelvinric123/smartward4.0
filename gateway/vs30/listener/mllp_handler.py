class MLLPHandler:
    """
    Handles MLLP (Minimal Lower Layer Protocol) framing for HL7 messages.
    
    MLLP Frame:
    <SB> Message <EB><CR>
    
    Where:
    <SB> = Start Block (0x0B)
    <EB> = End Block (0x1C)
    <CR> = Carriage Return (0x0D)
    """
    
    SB = b'\x0b'
    EB = b'\x1c'
    CR = b'\x0d'

    @staticmethod
    def wrap_message(message_bytes):
        """
        Wraps a raw message (bytes) in MLLP framing.
        """
        return MLLPHandler.SB + message_bytes + MLLPHandler.EB + MLLPHandler.CR

    @staticmethod
    def unwrap_message(data):
        """
        Unwraps MLLP-framed data.
        Returns a list of messages found in the buffer.
        """
        messages = []
        
        # Split by EB+CR
        raw_messages = data.split(MLLPHandler.EB + MLLPHandler.CR)
        
        for raw in raw_messages:
            if MLLPHandler.SB in raw:
                # Find start block and take everything after it
                start_index = raw.find(MLLPHandler.SB)
                clean_msg = raw[start_index + 1:]
                if clean_msg:
                    messages.append(clean_msg)
            elif len(raw) > 0:
                 # If no SB found but there is data, it might be a fragment or noise.
                 pass
                 
        return messages
