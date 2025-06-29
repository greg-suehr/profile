flowchart TD
 subgraph Client["Clients"]
        A["Web Browser"]
        B["Mobile App"]
  end
 subgraph Edge["Public Edge"]
        CDN["CDN"]
        DNS["DNS"]
        LB["Load Balancer"]
        APIGW["API Gateway / Webhook Dispatcher"]
  end
 subgraph WebApp["Web Layer"]
        WS["Web Server"]
        AS["Application Server"]
        PLUG["Plugin Sandbox"]
        AUTH["Auth Service"]
  end
 subgraph Data["Data & Persistence"]
        DBC["Redis DB Cache"]
        PG["Postgres Database"]
        REDIS["Redis Database"]
        CS["Cloud Storage"]
        BACKUP["Backup/Archival Service"]
  end
 subgraph Ext["External Services"]
        EMAIL["Email Service"]
        PAY["Payment Gateway"]
        MSG["Message Broker"]
        MON["Monitoring Broker"]
        LOGS["Log/Audit Aggregator"]
  end
    A -- HTTP/HTTPS --> CDN
    B -- HTTP/HTTPS --> CDN
    CDN -- "Pass-through" --> DNS
    DNS --> LB
    LB --> APIGW
    APIGW --> WS
    WS --> AS
    AS -- Plugin Calls --> PLUG
    AS -- Auth/OAuth --> AUTH
    APIGW -- REST/Webhooks --> AS
    AS -- Metrics/Logs --> MON
    AS -- Logs --> LOGS
    WS -- Metrics --> MON
    AS --> DBC & CS
    DBC --> PG & REDIS
    CS --> CDN
    PG --> BACKUP
    REDIS --> BACKUP
    BACKUP --> CS
    AS -- Outbound --> EMAIL
    AS -- Payments --> PAY
    AS -- Publish Events --> MSG
    PLUG --- comment2(["Plugin Sandbox: tenant-scoped, secure execution"])
    AUTH --- comment3(["Auth Service: supports OAuth, SSO, RBAC"])
    APIGW --- comment4(["API Gateway exposes REST and Webhook endpoints"])
    comment1(["Multi-tenant isolation: DB schema or row-level security"])

     AS:::mt
     PG:::mt
    classDef mt fill:#eee,stroke:#333,stroke-width:2px
    style Edge stroke:#38a,stroke-width:2px
    style WebApp stroke:#49a,stroke-width:2px
    style Data stroke:#194,stroke-width:2px


