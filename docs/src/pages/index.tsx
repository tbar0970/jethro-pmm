import type {ReactNode} from 'react';
import clsx from 'clsx';
import Link from '@docusaurus/Link';
import useDocusaurusContext from '@docusaurus/useDocusaurusContext';
import {useLatestVersion} from '@docusaurus/plugin-content-docs/client';
import Layout from '@theme/Layout';
import Heading from '@theme/Heading';

import styles from './index.module.css';


function HomepageHeader({docsPath}: {docsPath: string}) {
  const {siteConfig} = useDocusaurusContext();
  return (
    <header className={clsx('hero hero--primary', styles.heroBanner)}>
      <div className="container">
        <Heading as="h1" className="hero__title">
          {siteConfig.title}
        </Heading>
        <p className="hero__subtitle">{siteConfig.tagline}</p>
        <div className={styles.buttons}>
          <Link
            className="button button--secondary button--lg"
            to={`${docsPath}/user-manual/getting-started`}>
            Get Started →
          </Link>
          <Link
            className="button button--outline button--lg"
            to={`${docsPath}/user-manual/getting-started`}
            style={{marginLeft: '1rem'}}>
            How-to Guides
          </Link>
        </div>
      </div>
    </header>
  );
}

export default function Home(): ReactNode {
  const docsPath = useLatestVersion('default').path;
  return (
    <Layout
      title="Jethro ChMS Documentation"
      description="Documentation for Jethro church management software">
      <HomepageHeader docsPath={docsPath} />
      <main>
        <section className={styles.sections}>
          <div className="container">
            <div className="row">
              <div className="col col--3">
                <div className={styles.card}>
                  <Heading as="h3">📖 Tutorials</Heading>
                  <p>Step-by-step walkthroughs for new users. Start here if you're new to Jethro.</p>
                  <Link to={`${docsPath}/user-manual/getting-started`}>Browse tutorials →</Link>
                </div>
              </div>
              <div className="col col--3">
                <div className={styles.card}>
                  <Heading as="h3">🛠️ How-to Guides</Heading>
                  <p>Task-oriented guides for specific jobs: send SMS, add a person, take attendance.</p>
                  <Link to="/">Browse how-tos →</Link>
                </div>
              </div>
              <div className="col col--3">
                <div className={styles.card}>
                  <Heading as="h3">📚 Reference</Heading>
                  <p>Technical reference: architecture, database schema, API docs, configuration.</p>
                  <Link to="/">Browse reference →</Link>
                </div>
              </div>
              <div className="col col--3">
                <div className={styles.card}>
                  <Heading as="h3">💡 Explanation</Heading>
                  <p>Background and design rationale. Why things work the way they do.</p>
                  <Link to="/">Browse explanation →</Link>
                </div>
              </div>
            </div>
          </div>
        </section>
      </main>
    </Layout>
  );
}
