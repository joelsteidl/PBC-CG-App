(function ($, Drupal, drupalSettings) {
Drupal.behaviors.HighChartsBehavior = {
  attach: function (context, settings) {
    // Vars from drupalSettings.
    var settingsLabels = drupalSettings.highCharts.labels;
    var settingsSeriesData = drupalSettings.highCharts.seriesData;

    Highcharts.chart('container', {
        title: {
            text: ''
        },
        // subtitle: {
        //     text: 'Includes adults and guests.'
        // },
        xAxis: {
            categories: settingsLabels
        },
        yAxis: {
            title: {
                text: '# of People',
            },
            max: 250,
            min: 0,
     labels: {
        formatter: function() {
           return this.value;
        }
      },
        },
        legend: {
            layout: 'vertical',
            align: 'right',
            verticalAlign: 'middle'
        },

        tooltip: {
          shared: false,
          formatter: function() {
            // Get extra data.
            var extra = this.series.options.extra;
            // Find index of the current point.
            var index = this.series.data.indexOf(this.point);
            var display = extra[index];
            return display;
          }
        },
        series: settingsSeriesData,

        responsive: {
            rules: [{
                condition: {
                    maxWidth: 1200
                },
                chartOptions: {
                    legend: {
                        layout: 'horizontal',
                        align: 'center',
                        verticalAlign: 'bottom'
                    }
                }
            }]
        }
    });
  }
};
})(jQuery, Drupal, drupalSettings);
