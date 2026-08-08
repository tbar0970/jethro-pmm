# Jethro Functional Testing

Playwright tests against a shared devbox-managed Jethro instance
(`process-compose.yml`'s `functest_jethro_server` service).

For how scenarios get isolated configs from one shared backend, how the mock
SMS mock server works, and how to add a new scenario, see
[`docs/docs/developer/DEVELOPMENT_FUNCTESTS.md`](../../docs/docs/developer/DEVELOPMENT_FUNCTESTS.md).

```bash
devbox services up -b
devbox run functests
```
