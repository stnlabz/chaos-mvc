# ChAoS MVC Security Certification

**ChAoS Foundation — Security Qualification Program**

> [!IMPORTANT]
> Program development status: **Framework under definition.** This page describes the proposed security certification standard. No security certification, examination, or credential is awarded solely by reading this page.

## Purpose

ChAoS MVC Security Certification recognizes demonstrated ability to investigate, secure, test, and evaluate software built on the ChAoS MVC architecture. It emphasizes traceable evidence, protected Core boundaries, and repeatable verification over unsupported assertions.

The governing principle is simple: **Show the evidence.**

## Scope of assessment

A candidate must demonstrate competence appropriate to the specific role being assessed:

- **Framework security:** Understand and preserve Core, Router, Controller, Model, View, and userland boundaries.
- **Authentication and authorization:** Evaluate session handling, CSRF protection, role gates, and administrative operations.
- **Data integrity:** Use PDO, parameterized queries, scoped access, and explicit module-owned SQL installation and update lifecycles.
- **Module and theme integrity:** Apply the Example Module and Example Theme conventions without quietly changing Core.
- **Vulnerability investigation:** Reproduce reported defects, separate observation from inference, and produce defensible corrections.
- **Regression testing:** Run the applicable checks and preserve the outcome, including failures and untested areas.
- **Release qualification:** Evaluate a specific release candidate against the approved policy and required evidence.

## Evidence package

A security assessment must identify:

1. The exact repository, branch, and candidate commit SHA.
2. The applicable security requirements and approved test plan.
3. Files and interfaces examined, with source references.
4. Tests actually executed, commands or methods used, and their results.
5. Verified defects, severity, remediation, and remaining limitations.
6. The independent reviewer and recorded disposition.

A source excerpt, AI-generated explanation, or successful static check does **not** substitute for executed tests or proof of production behavior.

## Qualification outcomes

| Status | Meaning |
| --- | --- |
| {color:red}RED{/color} | A mandatory security or qualification gate failed. Release denied. |
| {color:amber}AMBER{/color} | Qualification is incomplete or has unresolved findings requiring review. Release not cleared. |
| {color:green}GREEN{/color} | All required gates for the specified candidate and scope passed. Eligible for release authorization. |

A GREEN verdict applies only to the assessed commit and the recorded qualification scope. A changed candidate requires reassessment. GREEN is not permission to deploy.

## Human and AI participants

Both human developers and AI engineering agents may be examined against clearly defined tasks, but a capability must be demonstrated rather than self-declared.

- **Developer / Digit role:** Investigate source, identify defects, propose or implement corrections within assigned authority, execute tests, and submit evidence.
- **Independent reviewer / Rictus role:** Challenge claims, check policy, independently examine evidence, and record a reasoned qualification decision.
- **Certification authority:** The ChAoS Foundation determines the approved criteria, examination, recognition, and any authorization attached to certification.

An assessor must not independently certify its own work. Rejections must specify the failed requirement and supporting evidence; approvals must identify the evidence that satisfied each required gate.

## Program governance

Final examination levels, passing thresholds, eligibility, renewal requirements, and issuance procedures remain subject to formal adoption by the ChAoS Foundation. This page does not issue or imply existing credentials.

For the broader developer certification program, see [ChAoS MVC Certification](/certification).

**Architecture without the shadows. Evidence before assertion.**
