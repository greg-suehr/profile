stateDiagram-v2
    direction LR

    state OpenOrders {
    [*] --> Open
    [*] --> Pending

    state IsApproved <<choice>>
    Pending --> IsApproved
    IsApproved --> Open: approve
    IsApproved --> Cancel : deny 

    Open --> Prep
    Prep --> Ready
    Ready --> Open
    Open --> Closed
    Pending --> Cancel
    Open --> Void
    Prep --> Void
    Ready --> Void
    }

    state ClosedPaid {
    Closed --> Paid
    }

   state ClosedUnpaid {
    Cancel
    Void
   }