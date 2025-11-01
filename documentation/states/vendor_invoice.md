stateDiagram-v2
    direction TB
    [*] --> VendorInvoice

    state VendorInvoice {
      direction LR

      state "Workflow" as vi_wf {
        [*] --> Draft
        Draft --> Posted : post
        Draft --> Voided : void
        Posted --> Voided : void_with_reversal
      }

      --
      state "Match" as vi_match {
        [*] --> Unmatched
        Unmatched --> PartiallyMatched : link_to_po_and_receipts
        PartiallyMatched --> Matched : resolve_all
        Unmatched --> Matched : auto_3way_match
        Matched --> Disputed : variance_detected
        Disputed --> Matched : variance_resolved
      }

      --
      state "Payment" as vi_pay {
        [*] --> Unpaid
        Unpaid --> PartiallyPaid : apply_payment
        PartiallyPaid --> Paid : apply_payment
        Paid --> PartiallyPaid : refund_or_chargeback
      }
    }
