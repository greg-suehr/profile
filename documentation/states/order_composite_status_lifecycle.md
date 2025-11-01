stateDiagram-v2
    direction TB
    
    [*] --> Purchase
    
    state Purchase {
        direction LR
        
        state "Workflow Status" as workflow {
            direction TB
            [*] --> Open
            [*] --> Draft
            
            Draft --> Open : approve
            Draft --> Cancel : deny
            Open --> Prep
            Prep --> Ready
            Ready --> Open : requeue
            Open --> Closed : fulfill
            Prep --> Closed : fulfill
            Ready --> Closed : fulfill
            
            Open --> Void
            Prep --> Void
            Ready --> Void
            
            Closed --> Open : reopen
            Cancel --> Pending : reopen
            Void --> Open : reopen
        }
        
        --
        
        state "Fulfillment Status" as fulfillment {
            direction TB
            [*] --> Unfulfilled
            Unfulfilled --> Partial
            Partial --> Complete
            Unfulfilled --> Complete
            Complete --> Partial : adjustment
        }
        
        --
        
        state "Billing Status" as billing {
            direction TB
            [*] --> Unbilled
            Unbilled --> Invoiced
            Invoiced --> PartialPaid : payment
            Invoiced --> Paid : payment
            PartialPaid --> Paid : payment
            Paid --> Refunded
            PartialPaid --> Refunded
        }
    }