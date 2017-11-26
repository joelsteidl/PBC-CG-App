(function ($, Drupal, drupalSettings) {
Drupal.behaviors.HighChartsBehavior = {
  attach: function (context, settings) {
    // Vars from drupalSettings.
    var settingsLabels = drupalSettings.highCharts.labels;
    var settingsSeriesData = drupalSettings.highCharts.seriesData;

    Highcharts.chart('container', {
        title: {
            text: 'CG Attendance'
        },
        subtitle: {
            text: 'Source: thesolarfoundation.com'
        },
        xAxis: {
            categories: settingsLabels
        },
        yAxis: {
            title: {
                text: 'Percent in Attendance',
            },
            max: 100,
            min: 0,
     labels: {
        formatter: function() {
           return this.value + '%';
        }
      },
        },
        legend: {
            layout: 'vertical',
            align: 'right',
            verticalAlign: 'middle'
        },

        tooltip: {
            formatter: function () {
                return 'The value for <b>' + this.x +
                    '</b> is <b>' + this.y + '</b>' + this.point.extra;
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
