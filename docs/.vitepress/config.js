export default {
  title: 'OZone Framework',
  description: 'A powerful framework to build RESTful APIs and websites',
  
  themeConfig: {
    logo: '/logo.png',
    
    nav: [
      { text: 'Guide', link: '/guide/' },
      { text: 'API Reference', link: '/api/' },
      { text: 'Examples', link: '/examples/' }
    ],

    sidebar: {
      '/guide/': [
        {
          text: 'Getting Started',
          items: [
            { text: 'Introduction', link: '/guide/' },
            { text: 'Installation', link: '/guide/installation' },
            { text: 'Configuration', link: '/guide/configuration' }
          ]
        },
        {
          text: 'Core Concepts',
          items: [
            { text: 'Routing', link: '/guide/routing' },
            { text: 'Controllers', link: '/guide/controllers' },
            { text: 'Views', link: '/guide/views' },
            { text: 'API Documentation', link: '/guide/api-docs' }
          ]
        }
      ],
      '/api/': [
        {
          text: 'API Reference',
          items: [
            { text: 'Overview', link: '/api/' },
            { text: 'Core Classes', link: '/api/core' },
            { text: 'REST API', link: '/api/rest' }
          ]
        }
      ]
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/silassare/ozone' }
    ],

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2017-present Emile Silas Sare'
    }
  },

  // Custom head tags
  head: [
    ['link', { rel: 'icon', type: 'image/png', href: '/logo.png' }]
  ]
}