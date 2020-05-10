/**
 * @layout false
 */
import React from "react";
import {Button, Checkbox, Divider, Form, Input} from "antd";
import {Box, Heading, Image} from 'rebass/styled-components';
import $ from 'miaoxing';
import app from '@miaoxing/app';
import logo from 'plugins/admin/resources/images/logo.png';
import {createGlobalStyle} from 'styled-components';
import api from '@miaoxing/api';
import {AForm, AFormItem} from '@miaoxing/form';

const GlobalStyle = createGlobalStyle`
  body {
    background: #f5f8fa url(https://image-10001577.image.myqcloud.com/uploads/3/20190602/15594729401485.jpg);
    background-size: cover;
  }
`;

export default class extends React.Component {
  state = {
    loading: false,
  }

  async componentDidMount() {
    const ret = await api.curPath('installed');
    if (ret.code !== 1) {
      $.alert(ret.message);
    }
  }

  showAgreement = async (e) => {
    e.preventDefault();

    const ret = await api.curPath('license');
    if (ret.code !== 1) {
      $.ret(ret);
      return;
    }

    const index = ret.content.indexOf('\n');
    const title = ret.content.substr(0, index);
    const content = ret.content.substr(index + 1)
      .replace(/\n\n/g, "<br/><br/>")
      .replace(/\n/g, '');

    $.alert({
      title,
      content,
      html: true,
    });
  }

  handleSubmit = async (values) => {
    this.setState({loading: true});
    const ret = await api.curCreate({
      data: values,
      loading: true,
    });
    this.setState({loading: false});
    if (ret.code !== 1) {
      $.alert(ret.message);
      return;
    }

    await $.ret(ret);
    window.location = ret.next;
  }

  render() {
    return <>
      <GlobalStyle/>
      <Box
        width={700}
        mx="auto"
        mt={5}
        p={5}
        bg="white"
      >
        <Box
          mb={4}
          textAlign="center"
        >
          <Image height={30} src={logo}/>
        </Box>
        <Heading
          mb={5}
          textAlign="center"
          fontSize={3}
          fontWeight="normal"
          color="muted"
        >
          安装
        </Heading>
        <AForm
          labelCol={{span: 8}}
          wrapperCol={{span: 8}}
          validateMessages={{
            required: '该项是必填的',
          }}
          initialValues={{
            dbHost: 'localhost',
            dbDbName: 'miaoxing',
            dbUser: 'root',
            dbTablePrefix: 'mx_',
            username: 'admin',
          }}
          onFinish={this.handleSubmit}
        >
          <AFormItem label="数据库地址" name="dbHost" rules={[{required: true}]}
            extra="如果有端口号，使用`:`隔开"
          />
          <AFormItem label="数据库名称" name="dbDbName" rules={[{required: true}]}/>
          <AFormItem label="数据库用户名" name="dbUser" rules={[{required: true}]}/>
          <AFormItem label="数据库密码" name="dbPassword" type="password" rules={[{required: true}]}/>
          <AFormItem label="数据表前缀" name="dbTablePrefix" rules={[{required: true}]}/>

          <Divider/>

          <AFormItem label="管理员用户名" name="username" rules={[{required: true}]}/>
          <AFormItem label="管理员密码" name="password"  type="password" rules={[{required: true}]}/>

          <Form.Item
            name="agree"
            wrapperCol={{offset: 8, span: 16}}
            valuePropName="checked"
            rules={[{required: true, message: "请阅读并同意《终端用户许可协议》"}]}
          >
            <Checkbox>我已阅读并同意<a href="#" onClick={this.showAgreement}>《终端用户许可协议》</a></Checkbox>
          </Form.Item>

          <Form.Item
            wrapperCol={{offset: 8, span: 8}}
          >
            <Button type="primary" htmlType="submit" block loading={this.state.loading}>
              安装
            </Button>
          </Form.Item>
        </AForm>
      </Box>
    </>;
  }
}
